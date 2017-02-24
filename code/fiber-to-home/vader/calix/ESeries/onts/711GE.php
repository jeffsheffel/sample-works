<?php
/*
 * 711GE
 * Get the configuration state for this type of ONT.
 * This module is called by: ftth/calix/gpon-request.php
 */

// Set the port configuration for this type of ONT
$num_EthGE_ports = 2;
$num_EthFE_ports = 0;
$num_OntPOTS_ports = 2;
$num_OntDS1_ports = 0;
$num_OntRfAvo_ports = 0;
$num_VideoRf_ports = 0;
$num_VideoHotRf_ports = 0;

include 'get-ont-state-700-series.php';
