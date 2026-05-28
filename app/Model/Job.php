<?php
/**
 * DataDachs – Job Model
 * Repräsentiert einen Verarbeitungsjob
 */

namespace DataDachs\Model;

class Job
{
    public string $id;
    public string $originalName;
    public string $filePath;
    public string $fileType;
    public string $status;
    public ?array $detectedRules;
    public ?array $confirmedRules;
    public ?string $resultPath;
    public int $createdAt;
    public int $expiresAt;
    public ?string $errorMessage;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->originalName = $data['original_name'];
        $this->filePath = $data['file_path'];
        $this->fileType = $data['file_type'];
        $this->status = $data['status'];
        $this->detectedRules = isset($data['detected_rules']) ? json_decode($data['detected_rules'], true) : null;
        $this->confirmedRules = isset($data['confirmed_rules']) ? json_decode($data['confirmed_rules'], true) : null;
        $this->resultPath = $data['result_path'] ?? null;
        $this->createdAt = (int) $data['created_at'];
        $this->expiresAt = (int) $data['expires_at'];
        $this->errorMessage = $data['error_message'] ?? null;
    }

    public function isExpired(): bool
    {
        return time() > $this->expiresAt;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'file_type' => $this->fileType,
            'status' => $this->status,
            'detected_rules' => $this->detectedRules,
            'confirmed_rules' => $this->confirmedRules,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'error_message' => $this->errorMessage,
        ];
    }
}
