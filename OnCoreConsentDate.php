<?php

namespace Stanford\OnCoreConsentDate;

use ExternalModules\ExternalModules;

require_once 'classes/Clients.php';
require_once 'classes/Protocols.php';

class OnCoreConsentDate extends \ExternalModules\AbstractExternalModule
{


    private array $protocols = [];

    private \Project $project;

    private array $mappedFields = [];
    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated

        if (isset($_GET['pid']) && $_GET['pid'] != '') {
            global $Proj;
            $this->setProject($Proj);
            $this->setProtocols();
        }
    }

    private function findREDCapMRNValue($record)
    {
        $mrnField = $this->getProjectSetting('redcap-mrn');
        foreach ($record as $event) {
            if ($event[$mrnField] && $event[$mrnField] != '') {
                return $event[$mrnField];
            }
        }
        throw new \Exception('No MRN for for this record');
    }

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        try {
            $redcapData = [];
            $param = array(
                'project_id' => $project_id,
                'return_format' => 'array',
//                'events' => $event_id,
                'records' => [$record]
            );
            $redcapRecord = \REDCap::getData($param);
            $mrn = $this->findREDCapMRNValue($redcapRecord[$record]);
            foreach ($this->getProtocols() as $protocol) {
                $demographic = $protocol->searchOnCoreSubjectsViaMRN($mrn);
                if (!empty($demographic)) {
                    if ($subject = $protocol->isProtocolHasSubjectDemographicId($demographic[0]['subjectDemographicsId'])) {
                        $consent = $protocol->getSubjectConsent($subject['protocolSubjectId']);
                        if (!empty($consent)) {
                            $redcapData =  $this->mapData($protocol->getProtocol());
                            $redcapData = array_merge($redcapData, $this->mapData($demographic[0]));
                            $redcapData = array_merge($redcapData, $this->mapData($consent[0]));
                            $this->mapOnCoreData($record, $redcapData);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function mapData($data){
        $keys = array_keys($data);
        $result = [];
        foreach ($this->getMappedFields() as $field){
            if(in_array($field['oncore-field'], $keys)){
                $result[$field['redcap-field']] = $data[$field['oncore-field']];
            }
        }
        return $result;
    }
    public function getFieldEvent($field)
    {
        $fieldForm = $this->getProject()->metadata[$field]['form_name'];
        foreach ($this->getProject()->eventsForms as $id => $event) {
            if (in_array($fieldForm, $event)) {
                return $id;
            }
        }
        throw new \Exception("$fieldForm is not part of any event!");
    }

    public function mapOnCoreData($recordId, $redcapData)
    {
        foreach ($redcapData as $key => $value) {
            $data = [];
            $data[\REDCap::getRecordIdField()] = $recordId;
            $eventId = $this->getFieldEvent($key);
            $data[$key] = $value;
            $data['redcap_event_name'] = $this->getProject()->getUniqueEventNames($eventId);
            $response = \REDCap::saveData($this->getProjectId(), 'json', json_encode(array($data)));
            if (!empty($response['errors'])) {
                if (is_array($response['errors'])) {
                    throw new \Exception(implode(",", $response['errors']));
                } else {
                    throw new \Exception($response['errors']);
                }

            }
        }
    }

    /**
     * @return Protocols[]
     */
    public function getProtocols(): array
    {
        if (!$this->protocols) {
            $this->setProtocols();
        }
        return $this->protocols;
    }

    /**
     * @param Protocols[] $protocols
     */
    public function setProtocols(): void
    {
        $protocolIds = ExternalModules::getProjectSetting($this->getPREFIX(), $this->getProjectId(), 'oncore-protocols');
        $protocols = [];
        foreach ($protocolIds as $id) {
            $protocols[] = new Protocols($id, $this->PREFIX);
        }
        $this->protocols = $protocols;
    }

    /**
     * @return \Project
     */
    public function getProject(): \Project
    {
        return $this->project;
    }

    /**
     * @param \Project $project
     */
    public function setProject(\Project $project): void
    {
        $this->project = $project;
    }

    /**
     * @return array
     */
    public function getMappedFields(): array
    {
        if(!$this->mappedFields){
            $this->setMappedFields();
        }
        return $this->mappedFields;
    }


    public function setMappedFields(): void
    {

        $this->mappedFields = $this->getSubSettings('fields-map');
    }


}
