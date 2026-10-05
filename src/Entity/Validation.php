<?php

namespace App\Entity;

use App\Repository\ValidationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: ValidationRepository::class)]
#[ORM\Table(name: 'validation')]
#[ORM\Index(name: 'validation_uid_idx', columns: ['uid'])]
#[ORM\Index(name: 'validation_owner_idx', columns: ['owner'])]
class Validation
{
    /**
     * User has uploaded a dataset but is yet to post the arguments
     * (the dataset is archived after max-age, see CleanupCommand).
     */
    public const STATUS_WAITING_ARGS = 'waiting_for_args';

    /**
     * The validation request by user has been recorded (both dataset and arguments received) but is yet to be carried out.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Validation is being carried out right now.
     */
    public const STATUS_PROCESSING = 'processing';

    /**
     * Validation is done and the results are available.
     */
    public const STATUS_FINISHED = 'finished';

    /**
     * A runtime error has occured.
     */
    public const STATUS_ERROR = 'error';

    /**
     * Files of the validation have been deleted (after max-age, see CleanupCommand, or delete-data argument),
     * the results are kept.
     */
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Allowed dataset names (used in file paths and command arguments, see column length).
     */
    public const REGEXP_DATASET_NAME = '/^[A-Za-z0-9_][A-Za-z0-9_.-]{0,99}$/';

    /**
     * Unique identifier.
     */
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 24, unique: true)]
    private string $uid;

    /**
     * Name of the dataset, derived from the name of the compressed file (zip) containing the dataset.
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private ?string $datasetName = null;

    /**
     * CLI Arguments for the Java executable program.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $arguments = null;

    /**
     * Date of creation.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    /**
     * Status (one of the STATUS_* constants, see also the CHECK constraint in the migrations).
     */
    #[ORM\Column(type: Types::STRING, length: 16, options: ['default' => self::STATUS_WAITING_ARGS])]
    private string $status;

    /**
     * Message (error message for STATUS_ERROR).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    /**
     * Start date.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateStart = null;

    /**
     * Finish date.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFinish = null;

    /**
     * Results in json format (validator-cli.jar report or zip pre-validation errors).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $results = null;

    /**
     * Document info (metadata extracted from the dataset by the validator), in json format.
     * Only available when the validation was run with the "normalize" argument enabled.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $documentInfo = null;

    /**
     * Delete the files as soon as the validation is done ("delete-data" argument).
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $deleteData = false;

    /**
     * Identifier (OIDC "sub") of the user who created the validation (null when OIDC is disabled).
     * Not exposed by the API (only to the admins, see ValidationsController::listValidations).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Exclude]
    private ?string $owner = null;

    /**
     * Name of the user who created the validation (when created), displayed to the admins.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Exclude]
    private ?string $ownerName = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->setDateCreation(new \DateTime('now'));
        $this->setStatus(self::STATUS_WAITING_ARGS);
        $this->setUid($this->generateUid());
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function setUid(string $uid): self
    {
        $this->uid = $uid;

        return $this;
    }

    public function getDatasetName(): ?string
    {
        return $this->datasetName;
    }

    /**
     * @throws \InvalidArgumentException if the name is not safe to be used in file paths
     */
    public function setDatasetName(string $datasetName): self
    {
        if (!self::isValidDatasetName($datasetName)) {
            throw new \InvalidArgumentException(sprintf("Invalid dataset name '%s'", $datasetName));
        }
        $this->datasetName = $datasetName;

        return $this;
    }

    /**
     * True if the dataset name is safe to be used in file paths and command arguments
     * (no "/", no "." or ".." and no leading "-").
     */
    public static function isValidDatasetName(?string $datasetName): bool
    {
        return null !== $datasetName && 1 === preg_match(self::REGEXP_DATASET_NAME, $datasetName);
    }

    public function getArguments(): ?array
    {
        return $this->arguments;
    }

    public function setArguments(?array $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDateStart(): ?\DateTimeInterface
    {
        return $this->dateStart;
    }

    public function setDateStart(?\DateTimeInterface $dateStart): self
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateFinish(): ?\DateTimeInterface
    {
        return $this->dateFinish;
    }

    public function setDateFinish(?\DateTimeInterface $dateFinish): self
    {
        $this->dateFinish = $dateFinish;

        return $this;
    }

    public function getResults(): ?array
    {
        return $this->results;
    }

    public function setResults(?array $results): self
    {
        $this->results = $results;

        return $this;
    }

    public function getDocumentInfo(): ?array
    {
        return $this->documentInfo;
    }

    public function setDocumentInfo(?array $documentInfo): self
    {
        $this->documentInfo = $documentInfo;

        return $this;
    }

    public function getDeleteData(): bool
    {
        return $this->deleteData;
    }

    public function setDeleteData(bool $deleteData): self
    {
        $this->deleteData = $deleteData;

        return $this;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(?string $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(?string $ownerName): self
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    /**
     * Reset all attributes because user has requested a validation with updated parameters.
     */
    public function reset(): self
    {
        $this->setStatus(self::STATUS_PENDING);
        $this->setMessage(null);
        $this->setDateStart(null);
        $this->setDateFinish(null);
        $this->setResults(null);
        $this->setDocumentInfo(null);

        return $this;
    }

    /**
     * Generate UID (lower case letters and digits).
     */
    private function generateUid(int $length = 24): string
    {
        $randomUid = '';

        for ($i = 0; $i < $length; ++$i) {
            if (1 == random_int(1, 2)) {
                // a digit between 0 and 9
                $randomUid .= chr(random_int(48, 57));
            } else {
                // a lowercase letter between a and z
                $randomUid .= chr(random_int(97, 122));
            }
        }

        return $randomUid;
    }
}
