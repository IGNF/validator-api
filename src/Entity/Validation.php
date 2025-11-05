<?php

namespace App\Entity;

use App\Repository\ValidationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ValidationRepository::class)
 * @ORM\Table(
 *      name="validation",
 *      indexes = {
 *          @ORM\Index(name="validation_uid_idx", columns={"uid"})
 *      }
 * )
 */
class Validation
{
    /**
     * User has uploaded a dataset but is yet to be considered
     */
    const STATUS_WAITING_ARGS = 'waiting_for_args';

    /**
     * The validation request by user is waiting to be uploaded to the validation API
     */
    const STATUS_UPLOADABLE = 'uploadable';

    /**
     * Validation is waiting to be patched with its args
     */
    const STATUS_PATCHABLE = 'patchable';

    /**
     * Validation is being processed by the API
     */
    const STATUS_WAITING_VALIDATION = 'waiting_valid';

    /**
     * Validation is done
     */
    const STATUS_VALIDATED = 'validated';

    /**
     * A runtime error has occured
     */
    const STATUS_ERROR = 'error';

    /**
     * Validation cancelled
     */
    const STATUS_ABORTED = "aborted";

    /**
     * Validation created 30 days ago and its files have been deleted automatically to save space on the server
     */
    const STATUS_ARCHIVED = 'archived';

    /**
     * Status where validation is stale
     */
    const STALE_STATUSES = [
        Validation::STATUS_ERROR,
        Validation::STATUS_ABORTED,
        Validation::STATUS_ARCHIVED
    ];

    /**
     * Work Statuses
     */
    const PENDING_STATUSES = [
        Validation::STATUS_WAITING_ARGS,
        Validation::STATUS_UPLOADABLE,
        Validation::STATUS_PATCHABLE,
        Validation::STATUS_WAITING_VALIDATION,
        Validation::STATUS_VALIDATED
    ];

    /**
     * Unique identifier
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=24, unique=true)
     */
    private $uid;

    /**
     * Name of the dataset, derived from the name of the compressed file (zip) containing the dataset
     *
     * @ORM\Column(type="string", length=100)
     */
    private $datasetName;

    /**
     * Model to use for validation
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $model;

    /**
     * SRS to use for validation
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $srs;

    /**
     * Whether to keep validation data
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $keepData;

    /**
     * Plugins to use during validation
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $plugins;

    /**
     * Date of creation
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateCreation;

    /**
     * Status
     *
     * @ORM\Column(type="string", length=16, nullable=true, options={"default":"waiting_for_args"}, columnDefinition="character varying(16) CHECK (status IN ('waiting_for_args','uploadable','patchable','waiting_valid','validated','error','aborted','archived'))")
     */
    private $status;

    /**
     * Message
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $message;

    /**
     * Start date
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateStart;

    /**
     * Finish date
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateFinish;

    /**
     * Results in json format
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private $results;

    /**
     * Api uid
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private string $apiId;


    /**
     * Whether the validation is currently being processed
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private bool $processing;

    /**
     * Constructor
     */
    public function __construct() {}

    public function getUid(): ?string
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

    public function setDatasetName(string $datasetName): self
    {
        $this->datasetName = $datasetName;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getSRS(): string
    {
        return $this->srs;
    }

    public function getKeepData(): bool
    {
        return $this->keepData;
    }

    public function setArguments(array $arguments): self
    {
        $this->model = $arguments['model'];
        $this->srs = $arguments['srs'];
        $this->keepData = $arguments['keepData'];

        return $this;
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function setPlugins(array $plugins): self
    {
        $this->plugins = $plugins;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getStatus(): ?string
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

    public function getResults()
    {
        return $this->results;
    }

    public function setResults($results)
    {
        $this->results = $results;

        return $this;
    }

    public function getApiId(): string
    {
        return $this->apiId;
    }

    public function setApiId(String $apiId): void
    {
        $this->apiId = $apiId;
    }

    public function getProcessing(): bool
    {
        return $this->processing;
    }

    public function setProcessing(bool $processing): self
    {
        $this->processing = $processing;
        return $this;
    }

    /**
     * Reset all attributes because user has requested a validation with updated parameters
     *
     * @return Validation
     */
    public function reset()
    {
        $this->setStatus($this::STATUS_WAITING_ARGS);
        $this->setMessage(null);
        $this->setDateStart(null);
        $this->setDateFinish(null);
        $this->setResults(null);

        return $this;
    }
}
