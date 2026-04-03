package git

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// CloneOptions contains options for cloning a repository
type CloneOptions struct {
	URL        string
	Branch     string
	CommitHash string
	Depth      int
	PrivateKey string
	Username   string
	Password   string
}

// Cloner handles git repository operations
type Cloner struct {
	workDir string
}

// NewCloner creates a new Cloner instance
func NewCloner(workDir string) *Cloner {
	return &Cloner{
		workDir: workDir,
	}
}

// Clone clones a repository to a temporary directory and returns the path
func (c *Cloner) Clone(ctx context.Context, opts CloneOptions) (string, error) {
	// Create unique directory for this clone
	timestamp := time.Now().UnixNano()
	cloneDir := filepath.Join(c.workDir, fmt.Sprintf("repo-%d", timestamp))

	if err := os.MkdirAll(cloneDir, 0755); err != nil {
		return "", fmt.Errorf("failed to create clone directory: %w", err)
	}

	// Prepare authentication
	repoURL, cleanup, err := c.prepareAuth(opts)
	if err != nil {
		os.RemoveAll(cloneDir)
		return "", fmt.Errorf("failed to prepare authentication: %w", err)
	}
	defer cleanup()

	// Build clone command
	args := []string{"clone"}

	// Add depth if specified
	if opts.Depth > 0 {
		args = append(args, "--depth", fmt.Sprintf("%d", opts.Depth))
	}

	// Add branch if specified
	if opts.Branch != "" && opts.Branch != "main" && opts.Branch != "master" {
		args = append(args, "--branch", opts.Branch)
	}

	args = append(args, repoURL, cloneDir)

	// Execute clone
	cmd := exec.CommandContext(ctx, "git", args...)
	cmd.Env = append(os.Environ(), "GIT_TERMINAL_PROMPT=0")

	output, err := cmd.CombinedOutput()
	if err != nil {
		os.RemoveAll(cloneDir)
		return "", fmt.Errorf("git clone failed: %w\nOutput: %s", err, string(output))
	}

	// If specific commit is requested, checkout that commit
	if opts.CommitHash != "" {
		if err := c.checkout(ctx, cloneDir, opts.CommitHash); err != nil {
			os.RemoveAll(cloneDir)
			return "", fmt.Errorf("failed to checkout commit: %w", err)
		}
	}

	return cloneDir, nil
}

// prepareAuth prepares authentication for git operations
func (c *Cloner) prepareAuth(opts CloneOptions) (string, func(), error) {
	cleanup := func() {}

	// If private key is provided, use SSH
	if opts.PrivateKey != "" {
		return c.prepareSSHAuth(opts)
	}

	// If username/password provided, embed in URL
	if opts.Username != "" && opts.Password != "" {
		return c.prepareHTTPAuth(opts)
	}

	// No authentication needed
	return opts.URL, cleanup, nil
}

// prepareSSHAuth sets up SSH authentication
func (c *Cloner) prepareSSHAuth(opts CloneOptions) (string, func(), error) {
	// Create temporary SSH key file
	keyFile, err := os.CreateTemp("", "git-key-*")
	if err != nil {
		return "", nil, fmt.Errorf("failed to create key file: %w", err)
	}

	if _, err := keyFile.WriteString(opts.PrivateKey); err != nil {
		keyFile.Close()
		os.Remove(keyFile.Name())
		return "", nil, fmt.Errorf("failed to write key: %w", err)
	}
	keyFile.Close()

	// Set correct permissions
	if err := os.Chmod(keyFile.Name(), 0600); err != nil {
		os.Remove(keyFile.Name())
		return "", nil, fmt.Errorf("failed to set key permissions: %w", err)
	}

	// Create GIT_SSH_COMMAND wrapper
	sshCmd := fmt.Sprintf("ssh -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null", keyFile.Name())
	os.Setenv("GIT_SSH_COMMAND", sshCmd)

	cleanup := func() {
		os.Remove(keyFile.Name())
		os.Unsetenv("GIT_SSH_COMMAND")
	}

	// Convert HTTPS URL to SSH if needed
	url := opts.URL
	if strings.HasPrefix(url, "https://github.com/") {
		url = strings.Replace(url, "https://github.com/", "git@github.com:", 1)
	} else if strings.HasPrefix(url, "https://gitlab.com/") {
		url = strings.Replace(url, "https://gitlab.com/", "git@gitlab.com:", 1)
	} else if strings.HasPrefix(url, "https://bitbucket.org/") {
		url = strings.Replace(url, "https://bitbucket.org/", "git@bitbucket.org:", 1)
	}

	return url, cleanup, nil
}

// prepareHTTPAuth embeds credentials in URL
func (c *Cloner) prepareHTTPAuth(opts CloneOptions) (string, func(), error) {
	url := opts.URL

	// Insert credentials into URL
	if strings.HasPrefix(url, "https://") {
		url = strings.Replace(url, "https://",
			fmt.Sprintf("https://%s:%s@", opts.Username, opts.Password), 1)
	} else if strings.HasPrefix(url, "http://") {
		url = strings.Replace(url, "http://",
			fmt.Sprintf("http://%s:%s@", opts.Username, opts.Password), 1)
	}

	return url, func() {}, nil
}

// checkout checks out a specific commit
func (c *Cloner) checkout(ctx context.Context, dir string, ref string) error {
	cmd := exec.CommandContext(ctx, "git", "checkout", ref)
	cmd.Dir = dir
	cmd.Env = append(os.Environ(), "GIT_TERMINAL_PROMPT=0")

	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("checkout failed: %w\nOutput: %s", err, string(output))
	}

	return nil
}

// GetLatestCommit returns the latest commit hash for a branch
func (c *Cloner) GetLatestCommit(ctx context.Context, dir string) (string, error) {
	cmd := exec.CommandContext(ctx, "git", "rev-parse", "HEAD")
	cmd.Dir = dir

	output, err := cmd.Output()
	if err != nil {
		return "", fmt.Errorf("failed to get latest commit: %w", err)
	}

	return strings.TrimSpace(string(output)), nil
}

// GetCommitInfo returns information about a commit
func (c *Cloner) GetCommitInfo(ctx context.Context, dir string) (*CommitInfo, error) {
	// Get commit hash
	hashCmd := exec.CommandContext(ctx, "git", "rev-parse", "HEAD")
	hashCmd.Dir = dir
	hashOutput, err := hashCmd.Output()
	if err != nil {
		return nil, fmt.Errorf("failed to get commit hash: %w", err)
	}

	// Get commit message
	msgCmd := exec.CommandContext(ctx, "git", "log", "-1", "--format=%s")
	msgCmd.Dir = dir
	msgOutput, err := msgCmd.Output()
	if err != nil {
		return nil, fmt.Errorf("failed to get commit message: %w", err)
	}

	// Get author
	authorCmd := exec.CommandContext(ctx, "git", "log", "-1", "--format=%an <%ae>")
	authorCmd.Dir = dir
	authorOutput, err := authorCmd.Output()
	if err != nil {
		return nil, fmt.Errorf("failed to get commit author: %w", err)
	}

	// Get commit date
	dateCmd := exec.CommandContext(ctx, "git", "log", "-1", "--format=%ci")
	dateCmd.Dir = dir
	dateOutput, err := dateCmd.Output()
	if err != nil {
		return nil, fmt.Errorf("failed to get commit date: %w", err)
	}

	commitDate, _ := time.Parse("2006-01-02 15:04:05 -0700", strings.TrimSpace(string(dateOutput)))

	return &CommitInfo{
		Hash:    strings.TrimSpace(string(hashOutput)),
		Message: strings.TrimSpace(string(msgOutput)),
		Author:  strings.TrimSpace(string(authorOutput)),
		Date:    commitDate,
	}, nil
}

// CommitInfo contains information about a git commit
type CommitInfo struct {
	Hash    string
	Message string
	Author  string
	Date    time.Time
}

// Cleanup removes a cloned repository
func (c *Cloner) Cleanup(dir string) error {
	return os.RemoveAll(dir)
}

// ParseGitURL extracts provider, owner, and repo from a git URL
func ParseGitURL(url string) (provider, owner, repo string, err error) {
	// Remove .git suffix if present
	url = strings.TrimSuffix(url, ".git")

	// Handle SSH URLs
	if strings.HasPrefix(url, "git@") {
		// git@github.com:owner/repo
		parts := strings.SplitN(url, ":", 2)
		if len(parts) != 2 {
			return "", "", "", fmt.Errorf("invalid SSH URL format")
		}

		providerPart := strings.TrimPrefix(parts[0], "git@")
		pathParts := strings.Split(parts[1], "/")
		if len(pathParts) < 2 {
			return "", "", "", fmt.Errorf("invalid SSH URL path")
		}

		return extractProvider(providerPart), pathParts[0], pathParts[1], nil
	}

	// Handle HTTPS URLs
	if strings.HasPrefix(url, "https://") || strings.HasPrefix(url, "http://") {
		url = strings.TrimPrefix(url, "https://")
		url = strings.TrimPrefix(url, "http://")

		// Remove any credentials
		if idx := strings.Index(url, "@"); idx != -1 {
			url = url[idx+1:]
		}

		parts := strings.Split(url, "/")
		if len(parts) < 3 {
			return "", "", "", fmt.Errorf("invalid HTTPS URL format")
		}

		return extractProvider(parts[0]), parts[1], parts[2], nil
	}

	return "", "", "", fmt.Errorf("unsupported URL format: %s", url)
}

func extractProvider(host string) string {
	switch {
	case strings.Contains(host, "github"):
		return "github"
	case strings.Contains(host, "gitlab"):
		return "gitlab"
	case strings.Contains(host, "bitbucket"):
		return "bitbucket"
	default:
		return "git"
	}
}
