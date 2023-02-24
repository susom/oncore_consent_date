<?php

namespace Stanford\OnCoreConsentDate;

class Protocols extends Clients
{

    private int $protocolId;

    private $protocol = [];

    private $subjects = [];

    /**
     * @throws \Exception
     */
    public function __construct($protocolId, $prefix)
    {
        parent::__construct($prefix);
        $this->setProtocolId($protocolId);
        $this->setProtocol($protocolId);
    }

    /**
     * @return array
     */
    public function getProtocol(): array
    {
        return $this->protocol;
    }

    /**
     * @param int $protocolID
     */
    public function setProtocol(int $protocolID): void
    {
        try {
            $response = $this->get('protocols/' . $protocolID);

            if ($response->getStatusCode() < 300) {
                $this->protocol = json_decode($response->getBody(), true);

            }
        } catch (GuzzleException $e) {
            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                $responseBodyAsString = json_decode($response->getBody()->getContents(), true);
                throw new \Exception($responseBodyAsString['message']);
            } else {
                echo($e->getMessage());
            }
        } catch (\Exception $e) {
            Entities::createException($e->getMessage());
            echo $e->getMessage();
        }
    }

    /**
     * @return array
     */
    public function getSubjects(): array
    {
        if (!$this->subjects) {
            $this->setSubjects();
        }
        return $this->subjects;
    }

    public function searchOnCoreSubjectsViaMRN($mrn)
    {
        $response = $this->get('subjectDemographics?mrn=' . $mrn . '&subjectSource=OnCore');
        if ($response->getStatusCode() < 300) {
            return json_decode($response->getBody(), true);
        } else {
            throw new \Exception('can not pull subjects for ' . $this->getProtocolId());
        }
    }

    /**
     * @param array $subjects
     */
    public function setSubjects(): void
    {
        $response = $this->get('protocolSubjects?protocolId=' . $this->getProtocolId());
        if ($response->getStatusCode() < 300) {
            $subjects = json_decode($response->getBody(), true);
            $this->subjects = $subjects;
        } else {
            throw new \Exception('can not pull subjects for ' . $this->getProtocolId());
        }

    }

    /**
     * @param int $subjectDemographicsId
     * @return array
     * @throws \Exception
     */
    private function getSubjectDemographics($subjectDemographicsId): array
    {
        $response = $this->get('subjectDemographics/' . $subjectDemographicsId);
        if ($response->getStatusCode() < 300) {
            $demographic = json_decode($response->getBody(), true);
            return $demographic;
        } else {
            throw new \Exception('cant pull demographics for ' . $subjectDemographicsId);
        }
    }

    public function getSubjectConsent($protocolSubjectId): array
    {
        $response = $this->get('protocolSubjectConsents/?protocolSubjectId=' . $protocolSubjectId);
        if ($response->getStatusCode() < 300) {
            $consent = json_decode($response->getBody(), true);
            return $consent;
        } else {
            throw new \Exception('cant pull demographics for ' . $protocolSubjectId);
        }
    }
    /**
     * @return int
     */
    public function getProtocolId(): int
    {
        return $this->protocolId;
    }

    /**
     * @param int $protocolId
     */
    public function setProtocolId(int $protocolId): void
    {
        $this->protocolId = $protocolId;
    }

    public function isProtocolHasSubjectDemographicId($subjectDemographicId){
        foreach ($this->getSubjects() as $subject){
            if($subjectDemographicId == $subject['subjectDemographicsId']){
                return $subject;
            }
        }
        return false;
    }
}