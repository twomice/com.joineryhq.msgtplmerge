<?php

/**
 * Utility methods for msgtplmerge
 *
 */
class CRM_Msgtplmerge_Utils {

  /**
   * Borrowed heavily from CRM_Contact_Form_Task_PDFLetterCommon::postProcess(),
   * CiviCRM version 4.7.28.
   *
   * Create a merged document.
   *
   * @param Int $contactId
   * @param Int $messageTemplateId
   * @param Int $pdfFormatId
   * @param String $type
   * @param Int $source_contact_id
   * @return boolean
   * @throws \CRM_Core_Exception
   */
  public static function merge($contactId, $messageTemplateId, $pdfFormatId = 0, $type = 'pdf', $source_contact_id = NULL) {

    $html_message = civicrm_api3('messageTemplate', 'getValue', array(
      'return' => 'msg_html',
      'id' => $messageTemplateId,
    ));

    $formValues = array(
      'html_message' => $html_message,
    );
    list($formValues, $categories, $html_message, $messageToken, $returnProperties) = CRM_Contact_Form_Task_PDFLetterCommon::processMessageTemplate($formValues);

    $skipOnHold = FALSE;
    $skipDeceased = FALSE;
    $html = $activityIds = array();

    $tokenDetailParams = array('contact_id' => $contactId);

    list($contact) = CRM_Utils_Token::getTokenDetails($tokenDetailParams,
      $returnProperties,
      $skipOnHold,
      $skipDeceased,
      NULL,
      $messageToken,
      'CRM_Contact_Form_Task_PDFLetterCommon'
    );

    if (civicrm_error($contact)) {
      return FALSE;
    }

    $tokenHtml = CRM_Utils_Token::replaceContactTokens($html_message, $contact[$contactId], TRUE, $messageToken);
    $tokenHtml = CRM_Utils_Token::replaceHookTokens($tokenHtml, $contact[$contactId], $categories, TRUE);

    if (defined('CIVICRM_MAIL_SMARTY') && CIVICRM_MAIL_SMARTY) {
      $smarty = CRM_Core_Smarty::singleton();
      // also add the contact tokens to the template
      $smarty->assign_by_ref('contact', $contact);
      $tokenHtml = $smarty->fetch("string:$tokenHtml");
    }

    $html = $tokenHtml;

    $tee = CRM_Msgtplmerge_ConsoleTree::create()->start();

    $mimeType = self::getMimeType($type);
    // ^^ Useful side-effect: consistently throws error for unrecognized types.

    if ($type == 'pdf') {
      $fileName = "CiviLetter.$type";
      // echo output; note third parameter of CRM_Utils_PDF_Utils::html2pdf()
      // is TRUE, forcing return of PDF contents (otherwise it will send
      // Content-Type and Content-Disposition headers in anticipation of download,
      // and event though $tee is capturing the buffer, the headers will still
      // go out, breaking any in-browser workflow.
      echo CRM_Utils_PDF_Utils::html2pdf($html, $fileName, TRUE, $pdfFormatId);
    }
    else {
      // This execution path currently will never happen because we force
      // api params['document_type'] to 'pdf'.
      $fileName = "CiviLetter.$type";
      CRM_Utils_PDF_Document::html2doc($html, $fileName, $pdfFormatId);
    }

    $tee->stop();
    $content = file_get_contents($tee->getFileName(), NULL, NULL, NULL, 5);
    if (empty($content)) {
      throw new \CRM_Core_Exception("Failed to capture document content (type=$type)!");
    }

    // Create an activity to record this merge, if possible.
    if (empty($source_contact_id)) {
      $source_contact_id = CRM_Core_Session::singleton()->getLoggedInContactID();
    }
    if (!empty($source_contact_id)) {
      $result = civicrm_api3('Activity', 'create', array(
        'subject' => 'Merge PDF Letter via API',
        'source_contact_id' => $source_contact_id,
        'activity_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Print PDF Letter'),
        'activity_date_time' => date('YmdHis'),
        'details' => $html_message,
        'target_contact_id' => $contactId,
      ));
      $activityId = $result['id'];

      civicrm_api3('Attachment', 'create', array(
        'entity_table' => 'civicrm_activity',
        'entity_id' => $activityId,
        'name' => $fileName,
        'mime_type' => $mimeType,
        'content' => file_get_contents($tee->getFileName()),
      ));
    }

    return array(
      'tmp_file_name' => $tee->getFileName(),
      'activity_id' => $activityId,
    );

  }

  /**
   * Copied verbatim from CRM_Contact_Form_Task_PDFLetterCommon::getMimeType(),
   * CiviCRM version 4.7.28.
   *
   * Convert from a vague-type/file-extension to mime-type.
   *
   * @param string $type
   * @return string
   * @throws \CRM_Core_Exception
   */
  public static function getMimeType($type) {
    $mimeTypes = array(
      'pdf' => 'application/pdf',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'odt' => 'application/vnd.oasis.opendocument.text',
      'html' => 'text/html',
    );
    if (isset($mimeTypes[$type])) {
      return $mimeTypes[$type];
    }
    else {
      throw new \CRM_Core_Exception("Cannot determine mime type");
    }
  }

  public static function contactExists($cid) {
    return (bool) civicrm_api3('contact', 'getCount', array(
      'id' => $cid,
    ));
  }

  public static function msgTemplateExists($id) {
    return (bool) civicrm_api3('MessageTemplate', 'getCount', array(
      'id' => $id,
    ));
  }

}
