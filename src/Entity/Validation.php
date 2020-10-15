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
     * User has uploaded a dataset but is yet to post the arguments
     * User has 30 days to provide the arguments, otherwise the dataset will be deleted
     */
    const STATUS_WAITING_ARGS = 'waiting_for_args';

    /**
     * The validation request by user has been recorded (both dataset and arguments received) but is yet to be carried out
     */
    const STATUS_PENDING = 'pending';

    /**
     * Validation is being carried out right now
     */
    const STATUS_PROCESSING = 'processing';

    /**
     * Validation is done and the results are available
     */
    const STATUS_FINISHED = 'finished';

    /**
     * A runtime error has occured
     */
    const STATUS_ERROR = 'error';

    /**
     * Validation created 30 days ago and its files have been deleted automatically to save space on the server
     */
    const STATUS_ARCHIVED = 'archived';

    /**
     * Temporary storage directory for dataset and validation results files
     */
    const VALIDATIONS_DIRECTORY = './var/data/validations';

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
     * CLI Arguments for the Java executable program
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private $arguments;

    /**
     * Date of creation
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $dateCreation;

    /**
     * Status
     *
     * @ORM\Column(type="string", length=16, nullable=false, options={"default":"waiting_for_args"}, columnDefinition="character varying(16) CHECK (status IN ('waiting_for_args','pending','processing','finished','archived','error'))")
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
     * Results in jsonl format
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $results;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDateCreation(new \DateTime('now'));
        $this->setStatus($this::STATUS_WAITING_ARGS);
        $this->setUid($this->generateUid());
    }

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

    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    public function setArguments(string $arguments): self
    {
        $this->arguments = $arguments;

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

    public function getResults(): ?string
    {
        return $this->results;
    }

    public function setResults(?string $results): self
    {
        $this->results = $results;

        return $this;
    }

    public function getDirectory()
    {
        if ($_ENV['APP_ENV'] == 'test') {
            $directory = $this::VALIDATIONS_DIRECTORY . '/' . 'test' . '/' . $this->getUid();
        } else {
            $directory = $this::VALIDATIONS_DIRECTORY . '/' . $this->getUid();
        }

        // return \str_replace('/\\|\//', '/', $directory);
        return $directory;
    }

    public function reset()
    {
        $this->setStatus($this::STATUS_PENDING);
        $this->setMessage(null);
        $this->setDateStart(null);
        $this->setDateFinish(null);
        $this->setResults(null);

        return $this;
    }

    /**
     * Generate UID
     *
     * @param integer $length
     * @return string
     */
    private function generateUid($length = 24)
    {
        $uid = "";

        for ($i = 0; $i < $length; $i++) {
            if (rand(1, 2) == 1) {
                // a digit between 0 and 9
                $uid .= chr(rand(48, 57));
            } else {
                // a lowercase letter between a and z
                $uid .= chr(rand(97, 122));
            }
        }
        return $uid;
    }
}
