package buildpack

import (
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"strings"
)

type AppType string

const (
	TypeNodeJS AppType = "nodejs"
	TypePHP    AppType = "php"
	TypeGolang AppType = "golang"
	TypePython AppType = "python"
	TypeStatic AppType = "static"
	TypeDocker AppType = "docker"
)

type DetectionResult struct {
	Type         AppType
	Version      string
	BuildCommand string
	StartCommand string
	Port         int
}

// Detect analyzes the project directory and returns the app type
func Detect(projectPath string) (*DetectionResult, error) {
	// 1. Dockerfile present = custom image
	if fileExists(filepath.Join(projectPath, "Dockerfile")) {
		return &DetectionResult{
			Type: TypeDocker,
		}, nil
	}

	// 2. package.json = Node.js
	if fileExists(filepath.Join(projectPath, "package.json")) {
		return detectNodeJS(projectPath)
	}

	// 3. composer.json = PHP
	if fileExists(filepath.Join(projectPath, "composer.json")) {
		return detectPHP(projectPath)
	}

	// 4. go.mod = Go
	if fileExists(filepath.Join(projectPath, "go.mod")) {
		return detectGolang(projectPath)
	}

	// 5. requirements.txt or pyproject.toml = Python
	if fileExists(filepath.Join(projectPath, "requirements.txt")) ||
		fileExists(filepath.Join(projectPath, "pyproject.toml")) {
		return detectPython(projectPath)
	}

	// 6. index.html = Static site
	if fileExists(filepath.Join(projectPath, "index.html")) ||
		fileExists(filepath.Join(projectPath, "public/index.html")) ||
		fileExists(filepath.Join(projectPath, "dist/index.html")) {
		return &DetectionResult{
			Type: TypeStatic,
			Port: 80,
		}, nil
	}

	return nil, ErrUnknownAppType
}

var ErrUnknownAppType = &DetectionError{Message: "unknown app type"}

type DetectionError struct {
	Message string
}

func (e *DetectionError) Error() string {
	return e.Message
}

func detectNodeJS(path string) (*DetectionResult, error) {
	pkg, err := parsePackageJSON(filepath.Join(path, "package.json"))
	if err != nil {
		return nil, err
	}

	result := &DetectionResult{
		Type:    TypeNodeJS,
		Version: "20",
		Port:    3000,
	}

	// Detect Node version
	if engines, ok := pkg["engines"].(map[string]interface{}); ok {
		if node, ok := engines["node"].(string); ok {
			result.Version = extractMajorVersion(node)
		}
	}

	// Detect scripts
	if scripts, ok := pkg["scripts"].(map[string]interface{}); ok {
		if _, ok := scripts["build"]; ok {
			result.BuildCommand = "npm run build"
		}
		if _, ok := scripts["start"]; ok {
			result.StartCommand = "npm start"
		} else if _, ok := scripts["serve"]; ok {
			result.StartCommand = "npm run serve"
		}
	}

	// Detect framework
	if deps, ok := pkg["dependencies"].(map[string]interface{}); ok {
		if _, isNext := deps["next"]; isNext {
			result.StartCommand = "npm start"
			result.BuildCommand = "npm run build"
		}
		if _, isNuxt := deps["nuxt"]; isNuxt {
			result.StartCommand = "npm run start"
			result.BuildCommand = "npm run build"
		}
	}

	return result, nil
}

func detectPHP(path string) (*DetectionResult, error) {
	result := &DetectionResult{
		Type:         TypePHP,
		Version:      "8.3",
		Port:         8080,
		BuildCommand: "composer install --no-dev --optimize-autoloader",
	}

	// Check for Laravel
	if fileExists(filepath.Join(path, "artisan")) {
		result.StartCommand = "php artisan serve --host=0.0.0.0 --port=8080"
	} else {
		result.StartCommand = "php -S 0.0.0.0:8080 -t public"
	}

	return result, nil
}

func detectGolang(path string) (*DetectionResult, error) {
	result := &DetectionResult{
		Type:         TypeGolang,
		Version:      "1.22",
		Port:         8080,
		BuildCommand: "go build -o app .",
		StartCommand: "./app",
	}

	// Check go.mod for Go version
	content, err := os.ReadFile(filepath.Join(path, "go.mod"))
	if err == nil {
		re := regexp.MustCompile(`go (\d+\.\d+)`)
		if matches := re.FindStringSubmatch(string(content)); len(matches) > 1 {
			result.Version = matches[1]
		}
	}

	return result, nil
}

func detectPython(path string) (*DetectionResult, error) {
	result := &DetectionResult{
		Type:         TypePython,
		Version:      "3.12",
		Port:         8000,
		BuildCommand: "pip install -r requirements.txt",
	}

	// Check for common frameworks
	if fileExists(filepath.Join(path, "manage.py")) {
		// Django
		result.StartCommand = "python manage.py runserver 0.0.0.0:8000"
	} else if fileExists(filepath.Join(path, "app.py")) {
		// Flask
		result.StartCommand = "python app.py"
	} else if fileExists(filepath.Join(path, "main.py")) {
		result.StartCommand = "python main.py"
	}

	return result, nil
}

func fileExists(path string) bool {
	_, err := os.Stat(path)
	return err == nil
}

func parsePackageJSON(path string) (map[string]interface{}, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var pkg map[string]interface{}
	if err := json.Unmarshal(data, &pkg); err != nil {
		return nil, err
	}

	return pkg, nil
}

func extractMajorVersion(version string) string {
	// Handle semver ranges like ">=18", "^20", "20.x"
	re := regexp.MustCompile(`(\d+)`)
	if matches := re.FindStringSubmatch(version); len(matches) > 1 {
		return matches[1]
	}
	return version
}

func GetDockerfile(appType AppType, version string) string {
	switch appType {
	case TypeNodeJS:
		return "docker/nodejs/Dockerfile." + version
	case TypePHP:
		return "docker/php/Dockerfile." + version
	case TypeGolang:
		return "docker/golang/Dockerfile." + strings.Replace(version, ".", "", 1)
	case TypePython:
		return "docker/python/Dockerfile." + version
	case TypeStatic:
		return "docker/static/Dockerfile"
	default:
		return ""
	}
}
