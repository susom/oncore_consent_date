<?php

namespace Stanford\OnCoreConsentDate;

/** @var \Stanford\OnCoreConsentDate\OnCoreConsentDate $module */


try{
    echo '<pre>';
    #print_r($module->getProtocols()[0]->getSubjects());
    echo '</pre>';
}catch (\Exception $e){
    echo $e->getMessage();
}