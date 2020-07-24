<?php
// use CRM_Msgtplmerge_ExtensionUtil as E;

/**
 * MessageTemplate.Merge API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_message_template_Merge_spec(&$spec) {
  $spec['msg_template_id'] = array(
    'api.required' => 1,
    'description' => 'Value of `civicrm_msg_template.id` for desired template. If no such message template exists, an exception is thrown.',
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Message Template ID',
  );
  $spec['contact_id'] = array(
    'api.required' => 1,
    'description' => 'Value of `civicrm_contact.id` for desired contact. If no such contact exists, an exception is thrown.',
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Contact ID',
  );
  $spec['pdf_format_id'] = array(
    'api.required' => 0,
    'description' => 'Value of `civicrm_option_value.value` for the desired option in the \'PDF Formats\' option group. If omitted, the Default format is used. If given and no such PDF format exists, an exception is thrown.',
    'api.default' => 0,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'PDF Format ID',
  );
  $spec['document_type'] = array(
    'api.required' => 0,
    'description' => 'EXPERIMENTAL: Currently this is ignored and the value \'pdf\' is always used. One of: \'pdf\', \'docx\', \'odt\', \'html\'. Defaults to \'pdf\'.',
    'api.default' => 'pdf',
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Document Type',
  );
  $spec['source_contact_id'] = array(
    'api.required' => 0,
    'description' => 'Value of contact.id for contact to be recorded as the creator of the corresponding "Print PDF Letter" activity. If not given, the current logged in user is used. If no such contact exists, no activity is created.',
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'File Path',
  );
}

/**
 * MessageTemplate.Merge API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_message_template_Merge($params) {

  if (CRM_Utils_Array::value('destination', $params) == 'file' && !CRM_Utils_Array::value('filepath', $params)) {
    throw new API_Exception(
      "Mandatory key(s) missing from params array: filepath required when destination='file'.",
      "mandatory_missing",
      array("fields" => array('destination', 'filepath'))
    );
  }

  if (!CRM_Msgtplmerge_Utils::contactExists($params['contact_id'])) {
    throw new API_Exception(
      "contact_id is not valid: {$params['contact_id']}",
      "contact_not_found",
      array("fields" => array('contact_id'))
    );
  }
  if (!CRM_Msgtplmerge_Utils::msgTemplateExists($params['msg_template_id'])) {
    throw new API_Exception(
      "msg_template_id is not valid: {$params['msg_template_id']}",
      "msg_template_not_found",
      array("fields" => array('msg_template_id'))
    );
  }

  // Force file format to PDF.
  $params['document_type'] = 'pdf';

  $returnValues = CRM_Msgtplmerge_Utils::merge($params['contact_id'], $params['msg_template_id'], $params['pdf_format_id'], $params['document_type'], $params['source_contact_id']);

  if (CRM_Utils_Array::value('tmp_file_name', $returnValues)) {
    return civicrm_api3_create_success(array($returnValues), $params, 'MessageTemplate', 'merge');
  }
  else {
    return civicrm_api3_create_error('Document creation failed.', $params);
  }

}
