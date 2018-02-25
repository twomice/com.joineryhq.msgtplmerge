<?php

/**
 * Extension of CRM_Utils_ConsoleTee to allow special handling.
 *
 */
class CRM_Msgtplmerge_ConsoleTree extends CRM_Utils_ConsoleTee {

  /**
   * Stop capturing console output, and delete buffer.
   * Overrides parent::stop(), which stops capturing and FLUSHES the buffer.
   * 
   * @param Boolean $isClean If FALSE, just call parent::stop(); otherwise,
   *   write the buffer to a file (just like parent does) but use ob_end_clean()
   *   instead of ob_end_flush().
   *
   * @return CRM_Msgtplmerge_ConsoleTree
   */
  public function stop($isClean = TRUE) {
    if ($isClean) {
      ob_end_clean();
      fclose($this->fh);
      return $this;
    } 
    else {
      return parent::stop();
    }
  }  

}
