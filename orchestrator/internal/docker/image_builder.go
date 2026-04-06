package docker

import (
	"archive/tar"
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
	"time"

	"github.com/docker/docker/api/types"
	"github.com/docker/docker/api/types/image"
	"github.com/docker/docker/api/types/registry"
	"github.com/docker/docker/client"
	"github.com/docker/docker/pkg/archive"
)

// BuildOptions contains options for building a Docker image
type BuildOptions struct {
	ContextPath    string
	Dockerfile     string
	ImageName      string
	ImageTag       string
	BuildArgs      map[string]string
	Labels         map[string]string
	NoCache        bool
	Pull           bool
	Target         string
	Platform       string
}

// PushOptions contains options for pushing an image
type PushOptions struct {
	ImageName string
	ImageTag  string
	Registry  string
	Username  string
	Password  string
}

// BuildResult contains the result of a build
type BuildResult struct {
	ImageID   string
	ImageName string
	ImageTag  string
	Size      int64
	Duration  time.Duration
	Logs      string
}

// ImageBuilder handles Docker image building
type ImageBuilder struct {
	client       *client.Client
	buildpackDir string
}

// NewImageBuilder creates a new ImageBuilder instance
func NewImageBuilder(buildpackDir string) (*ImageBuilder, error) {
	cli, err := client.NewClientWithOpts(client.FromEnv, client.WithAPIVersionNegotiation())
	if err != nil {
		return nil, fmt.Errorf("failed to create docker client: %w", err)
	}

	return &ImageBuilder{
		client:       cli,
		buildpackDir: buildpackDir,
	}, nil
}

// Build builds a Docker image from a context path
func (b *ImageBuilder) Build(ctx context.Context, opts BuildOptions, logCallback func(string)) (*BuildResult, error) {
	startTime := time.Now()
	var logs bytes.Buffer

	// Create build context tar
	buildContext, err := b.createBuildContext(opts.ContextPath, opts.Dockerfile)
	if err != nil {
		return nil, fmt.Errorf("failed to create build context: %w", err)
	}

	// Prepare image tag
	fullImageName := opts.ImageName
	if opts.ImageTag != "" {
		fullImageName = fmt.Sprintf("%s:%s", opts.ImageName, opts.ImageTag)
	}

	// Prepare build args
	buildArgs := make(map[string]*string)
	for k, v := range opts.BuildArgs {
		value := v
		buildArgs[k] = &value
	}

	// Build options
	buildOpts := types.ImageBuildOptions{
		Tags:        []string{fullImageName},
		Dockerfile:  opts.Dockerfile,
		BuildArgs:   buildArgs,
		Labels:      opts.Labels,
		NoCache:     opts.NoCache,
		PullParent:  opts.Pull,
		Target:      opts.Target,
		Platform:    opts.Platform,
		Remove:      true,
		ForceRemove: true,
	}

	// Execute build
	response, err := b.client.ImageBuild(ctx, buildContext, buildOpts)
	if err != nil {
		return nil, fmt.Errorf("build failed: %w", err)
	}
	defer response.Body.Close()

	// Process build output
	var imageID string
	decoder := json.NewDecoder(response.Body)
	for {
		var msg struct {
			Stream      string `json:"stream"`
			Error       string `json:"error"`
			ErrorDetail struct {
				Message string `json:"message"`
			} `json:"errorDetail"`
			Aux struct {
				ID string `json:"ID"`
			} `json:"aux"`
		}

		if err := decoder.Decode(&msg); err != nil {
			if err == io.EOF {
				break
			}
			continue
		}

		if msg.Error != "" {
			return nil, fmt.Errorf("build error: %s", msg.Error)
		}

		if msg.Stream != "" {
			logs.WriteString(msg.Stream)
			if logCallback != nil {
				logCallback(msg.Stream)
			}
		}

		if msg.Aux.ID != "" {
			imageID = msg.Aux.ID
		}
	}

	// Get image info
	imageInfo, _, err := b.client.ImageInspectWithRaw(ctx, fullImageName)
	if err != nil {
		return nil, fmt.Errorf("failed to inspect image: %w", err)
	}

	return &BuildResult{
		ImageID:   imageID,
		ImageName: opts.ImageName,
		ImageTag:  opts.ImageTag,
		Size:      imageInfo.Size,
		Duration:  time.Since(startTime),
		Logs:      logs.String(),
	}, nil
}

// BuildWithBuildpack builds using a predefined buildpack
func (b *ImageBuilder) BuildWithBuildpack(ctx context.Context, appType, version, contextPath, imageName, imageTag string, envVars map[string]string, logCallback func(string)) (*BuildResult, error) {
	// Get buildpack Dockerfile
	dockerfilePath, err := b.getBuildpackDockerfile(appType, version)
	if err != nil {
		return nil, fmt.Errorf("failed to get buildpack: %w", err)
	}

	// Copy Dockerfile to context
	dockerfileContent, err := os.ReadFile(dockerfilePath)
	if err != nil {
		return nil, fmt.Errorf("failed to read buildpack Dockerfile: %w", err)
	}

	destDockerfile := filepath.Join(contextPath, "Dockerfile.buildpack")
	if err := os.WriteFile(destDockerfile, dockerfileContent, 0644); err != nil {
		return nil, fmt.Errorf("failed to write Dockerfile: %w", err)
	}
	defer os.Remove(destDockerfile)

	// Prepare labels
	labels := map[string]string{
		"easydeploy.managed":     "true",
		"easydeploy.app.type":    appType,
		"easydeploy.app.version": version,
		"easydeploy.built.at":    time.Now().Format(time.RFC3339),
	}

	opts := BuildOptions{
		ContextPath: contextPath,
		Dockerfile:  "Dockerfile.buildpack",
		ImageName:   imageName,
		ImageTag:    imageTag,
		BuildArgs:   envVars,
		Labels:      labels,
		Pull:        true,
		NoCache:     true, // Force fresh build to ensure buildpack changes take effect
	}

	return b.Build(ctx, opts, logCallback)
}

// getBuildpackDockerfile returns the path to the buildpack Dockerfile
func (b *ImageBuilder) getBuildpackDockerfile(appType, version string) (string, error) {
	var dockerfileName string

	switch appType {
	case "nodejs":
		if version == "" {
			version = "20"
		}
		dockerfileName = fmt.Sprintf("nodejs/Dockerfile.%s", version)
	case "php":
		if version == "" {
			version = "8.3"
		}
		dockerfileName = fmt.Sprintf("php/Dockerfile.%s", version)
	case "golang":
		if version == "" {
			version = "1.22"
		}
		dockerfileName = fmt.Sprintf("golang/Dockerfile.%s", version)
	case "python":
		if version == "" {
			version = "3.12"
		}
		dockerfileName = fmt.Sprintf("python/Dockerfile.%s", version)
	case "static":
		dockerfileName = "static/Dockerfile"
	default:
		return "", fmt.Errorf("unsupported app type: %s", appType)
	}

	path := filepath.Join(b.buildpackDir, dockerfileName)
	if _, err := os.Stat(path); os.IsNotExist(err) {
		return "", fmt.Errorf("buildpack not found: %s", path)
	}

	return path, nil
}

// createBuildContext creates a tar archive of the build context
func (b *ImageBuilder) createBuildContext(contextPath, dockerfile string) (io.ReadCloser, error) {
	// Use Docker's archive package for proper handling
	tar, err := archive.TarWithOptions(contextPath, &archive.TarOptions{
		ExcludePatterns: []string{
			".git",
			".gitignore",
			"node_modules",
			"vendor",
			".env",
			".env.*",
			"*.log",
			".DS_Store",
		},
	})
	if err != nil {
		return nil, fmt.Errorf("failed to create tar archive: %w", err)
	}

	return tar, nil
}

// Push pushes an image to a registry
func (b *ImageBuilder) Push(ctx context.Context, opts PushOptions, logCallback func(string)) error {
	fullImageName := opts.ImageName
	if opts.ImageTag != "" {
		fullImageName = fmt.Sprintf("%s:%s", opts.ImageName, opts.ImageTag)
	}

	// Tag image with registry prefix if needed
	registryImage := fullImageName
	if opts.Registry != "" && !strings.HasPrefix(opts.ImageName, opts.Registry) {
		registryImage = fmt.Sprintf("%s/%s", opts.Registry, fullImageName)
		if err := b.client.ImageTag(ctx, fullImageName, registryImage); err != nil {
			return fmt.Errorf("failed to tag image: %w", err)
		}
	}

	// Prepare auth config
	var authStr string
	if opts.Username != "" && opts.Password != "" {
		authConfig := registry.AuthConfig{
			Username: opts.Username,
			Password: opts.Password,
		}
		encodedJSON, err := json.Marshal(authConfig)
		if err != nil {
			return fmt.Errorf("failed to encode auth: %w", err)
		}
		authStr = base64.URLEncoding.EncodeToString(encodedJSON)
	}

	// Push image
	response, err := b.client.ImagePush(ctx, registryImage, image.PushOptions{
		RegistryAuth: authStr,
	})
	if err != nil {
		return fmt.Errorf("push failed: %w", err)
	}
	defer response.Close()

	// Process push output
	decoder := json.NewDecoder(response)
	for {
		var msg struct {
			Status      string `json:"status"`
			Progress    string `json:"progress"`
			Error       string `json:"error"`
			ErrorDetail struct {
				Message string `json:"message"`
			} `json:"errorDetail"`
		}

		if err := decoder.Decode(&msg); err != nil {
			if err == io.EOF {
				break
			}
			continue
		}

		if msg.Error != "" {
			return fmt.Errorf("push error: %s", msg.Error)
		}

		if logCallback != nil && msg.Status != "" {
			if msg.Progress != "" {
				logCallback(fmt.Sprintf("%s %s\n", msg.Status, msg.Progress))
			} else {
				logCallback(fmt.Sprintf("%s\n", msg.Status))
			}
		}
	}

	return nil
}

// RemoveImage removes a local image
func (b *ImageBuilder) RemoveImage(ctx context.Context, imageID string, force bool) error {
	_, err := b.client.ImageRemove(ctx, imageID, image.RemoveOptions{
		Force:         force,
		PruneChildren: true,
	})
	return err
}

// createTarArchive creates a tar archive from a directory (fallback implementation)
func createTarArchive(contextPath string, excludePatterns []string) (*bytes.Buffer, error) {
	buf := new(bytes.Buffer)
	tw := tar.NewWriter(buf)
	defer tw.Close()

	err := filepath.Walk(contextPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}

		// Get relative path
		relPath, err := filepath.Rel(contextPath, path)
		if err != nil {
			return err
		}

		// Skip excluded patterns
		for _, pattern := range excludePatterns {
			if matched, _ := filepath.Match(pattern, relPath); matched {
				if info.IsDir() {
					return filepath.SkipDir
				}
				return nil
			}
			if matched, _ := filepath.Match(pattern, info.Name()); matched {
				if info.IsDir() {
					return filepath.SkipDir
				}
				return nil
			}
		}

		// Create tar header
		header, err := tar.FileInfoHeader(info, "")
		if err != nil {
			return err
		}
		header.Name = relPath

		// Handle symlinks
		if info.Mode()&os.ModeSymlink != 0 {
			link, err := os.Readlink(path)
			if err != nil {
				return err
			}
			header.Linkname = link
		}

		if err := tw.WriteHeader(header); err != nil {
			return err
		}

		// Write file content
		if !info.IsDir() && info.Mode().IsRegular() {
			file, err := os.Open(path)
			if err != nil {
				return err
			}
			defer file.Close()

			if _, err := io.Copy(tw, file); err != nil {
				return err
			}
		}

		return nil
	})

	return buf, err
}

// Close closes the Docker client connection
func (b *ImageBuilder) Close() error {
	return b.client.Close()
}
