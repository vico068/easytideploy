package queue

import (
	"context"
	"encoding/json"
	"time"

	"github.com/go-redis/redis/v8"
)

type RedisQueue struct {
	client *redis.Client
}

type BuildJob struct {
	DeploymentID  string            `json:"deployment_id"`
	ApplicationID string            `json:"application_id"`
	GitRepository string            `json:"git_repository"`
	GitBranch     string            `json:"git_branch"`
	CommitSHA     string            `json:"commit_sha,omitempty"`
	GitToken      string            `json:"git_token,omitempty"`
	Type          string            `json:"type"`
	BuildCommand  string            `json:"build_command"`
	StartCommand  string            `json:"start_command"`
	RootDirectory string            `json:"root_directory,omitempty"`
	Port          int               `json:"port"`
	Replicas      int               `json:"replicas"`
	CPULimit      int               `json:"cpu_limit"`
	MemoryLimit   int               `json:"memory_limit"`
	Environment   map[string]string `json:"environment"`
	HealthCheck   *HealthCheck      `json:"health_check"`
	CallbackURL   string            `json:"callback_url,omitempty"`
}

type HealthCheck struct {
	Path     string `json:"path"`
	Interval int    `json:"interval"`
}

func NewRedisQueue(redisURL string) (*RedisQueue, error) {
	opt, err := redis.ParseURL(redisURL)
	if err != nil {
		return nil, err
	}

	client := redis.NewClient(opt)

	// Test connection
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := client.Ping(ctx).Err(); err != nil {
		return nil, err
	}

	return &RedisQueue{client: client}, nil
}

func (q *RedisQueue) Close() error {
	return q.client.Close()
}

func (q *RedisQueue) Enqueue(queueName string, job interface{}) error {
	data, err := json.Marshal(job)
	if err != nil {
		return err
	}

	return q.client.LPush(context.Background(), queueName, data).Err()
}

func (q *RedisQueue) Dequeue(queueName string, timeout time.Duration) ([]byte, error) {
	result, err := q.client.BRPop(context.Background(), timeout, queueName).Result()
	if err != nil {
		if err == redis.Nil {
			return nil, nil
		}
		return nil, err
	}

	if len(result) < 2 {
		return nil, nil
	}

	return []byte(result[1]), nil
}

func (q *RedisQueue) Remove(queueName string, jobID string) error {
	// This is a simplified implementation
	// In production, you'd want to use a more sophisticated approach
	return q.client.LRem(context.Background(), queueName, 0, jobID).Err()
}

func (q *RedisQueue) Len(queueName string) (int64, error) {
	return q.client.LLen(context.Background(), queueName).Result()
}
