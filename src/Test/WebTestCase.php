<?php

namespace App\Test;

use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase as BaseWebTestCase;

/**
 * Description of GcmsTestCase
 *
 * @author RPas
 */
abstract class WebTestCase extends BaseWebTestCase
{
    /**
     *
     * @var AbstractExecutor
     */
    protected $fixtures;

    /**
     * Retourne les fixtures
     * @return ReferenceRepository
     */
    public function getFixtures()
    {
        return $this->fixtures;
    }

    /**
     * Retourne la référence d'une fixture
     * @param type $name Nom de la référence
     * @return mixed (souvent une entité)
     * @throws \Exception
     */
    public function getReference($name)
    {
        if ($this->fixtures->getReferenceRepository()->hasReference($name)) {
            return $this->fixtures->getReferenceRepository()->getReference($name);
        } else {
            throw new \Exception("No reference found for $name");
        }
    }

}
