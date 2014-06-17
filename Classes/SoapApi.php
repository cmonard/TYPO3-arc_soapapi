<?php

namespace Archriss\ArcSoapapi;

/**
 * Api for SOAP connection made easier, WSDL only
 *
 * All your soap function should be accessible in this object directly
 * You can xclass arc_soapapi to implement your own soap call if you need particular calling method over some functions
 *
 * @author	Christophe Monard (Archriss) <cmonard@archriss.com>
 *
 * calling method:
 * 	$soapLib = t3lib_div::makeInstance('Archriss\\ArcSoapapi\\SoapApi');
 *  $return = $soapLib->myWebServiceFunction($parameter1, $parameter2);
 *  $return = $soapLib->myWebServiceFunction($parameterArray);
 *  // list function - if SHOWFUNC is checked
 *  echo $soapLib->listFunc;
 *  // display last Request / Respond - if TRACE is checked
 *  echo $soapLib->lastRR;
 *  // display last error
 *  echo $soapLib->error;
 *
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *   48: class SoapApi
 *   97:    protected function init($conf)
 *  131:    protected function getValue($conf, $val)
 *  161:    public function __construct()
 *  178:    public function __destruct()
 *  182:    public function getClient()
 *  186:    public function __call($method, $arguments)
 *  213:    protected function SOAP_RR_debug()
 *  221:    protected function sendMail($param = array())
 *  246:    private function getHook($hookName, &$hookConf = array())
 *
 * TOTAL FUNCTIONS: 9
 *
 */

/**
 * General SOAP client class
 *
 * @author	Christophe Monard <cmonard@archriss.com>
 * @package	TYPO3
 * @subpackage	tx_arcsoapapi
 */
class SoapApi {

        protected $error_mail = '';
        protected $bindcopie = '';
        protected $wsdl = FALSE;
        protected $client;
        protected $finalURI = '';
        protected $uri = array(
            0 => 'http',
            1 => '',
            2 => '://',
            3 => '',
            4 => ':',
            5 => '',
            6 => '/',
            7 => ''
        );
        protected $allowedUriOption = array(
            'SSL' => array('field' => 1, 'type' => 'ssl'),
            'HOST' => array('field' => 3, 'type' => ''),
            'PORT' => array('field' => 5, 'type' => 'integer'),
            'SCHEME' => array('field' => 7, 'type' => ''),
        );
        protected $soapConf = array();
        protected $allowedSoapOption = array(
            'SOAP' => array('field' => 'soap_version', 'type' => ''),
            'LOGIN' => array('field' => 'login', 'type' => 'none'),
            'PASSWORD' => array('field' => 'password', 'type' => 'none'),
            'PROXYHOST' => array('field' => 'proxy_host', 'type' => 'none'),
            'PROXYPORT' => array('field' => 'proxy_port', 'type' => 'integer'),
            'PROXYUSERNAME' => array('field' => 'proxy_login', 'type' => 'none'),
            'PROXYPASSWORD' => array('field' => 'proxy_password', 'type' => 'none'),
            'COMPRESSION' => array('field' => 'compression', 'type' => 'zip'),
            'EXCEPTIONS' => array('field' => 'exceptions', 'type' => 'boolean'),
            'TRACE' => array('field' => 'trace', 'type' => 'boolean'),
            'CACHE' => array('field' => 'cache_wsdl', 'type' => ''),
        );
        protected $allowedProperties = array(
            'WSDL' => array('field' => 'wsdl', 'type' => 'boolean'),
            'SHOWFUNC' => array('field' => 'showfunc', 'type' => 'boolean'),
            'ERROR_MAIL' => array('field' => 'error_mail', 'type' => 'mail'),
            'BIND_MAIL' => array('field' => 'bindcopie', 'type' => 'mail'),
        );
        public $error = '';
        public $listFunc = '';
        public $lastRR = '';

        protected function init($conf) {
                $this->getHook('preInit');
                if (is_null($conf)) {
                        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['arc_soapapi']);
                }
                if (is_array($conf)) {
                        $soapOptionKey = array_keys($this->allowedSoapOption);
                        $uriOptionKey = array_keys($this->allowedUriOption);
                        $propertiesKey = array_keys($this->allowedProperties);
                        foreach ($conf as $confKey => $confValue) {
                                $confKey = strtoupper($confKey);
                                if (in_array($confKey, $soapOptionKey)) {
                                        $val = $this->getValue($this->allowedSoapOption[$confKey]['type'], $confValue);
                                        if ($val != '')
                                                $this->soapConf[$this->allowedSoapOption[$confKey]['field']] = $val;
                                } elseif (in_array($confKey, $uriOptionKey)) {
                                        $val = $this->getValue($this->allowedUriOption[$confKey]['type'], $confValue);
                                        if ($val != '')
                                                $this->uri[$this->allowedUriOption[$confKey]['field']] = $val;
                                } elseif (in_array($confKey, $propertiesKey)) {
                                        $field = $this->allowedProperties[$confKey]['field'];
                                        $this->$field = $this->getValue($this->allowedProperties[$confKey]['type'], $confValue);
                                }
                        }
                        if ($this->uri[5] == 0)
                                unset($this->uri[4], $this->uri[5]);
                        $this->finalURI = implode('', $this->uri);
                        if (!$this->wsdl) {
                                unset($this->uri[7]);
                                $this->soapConf['location'] = $this->finalURI;
                                $this->soapConf['uri'] = implode('', $this->uri);
                        }
                }
                $this->getHook('postInit');
        }

        protected function getValue($conf, $val) {
                switch ($conf) {
                        case 'none':
                                $return = ($val != '' ? $val : NULL);
                                break;
                        case 'integer':
                                $return = intval($val);
                                if ($return == 0)
                                        $return = '';
                                break;
                        case 'boolean':
                                $return = $val ? TRUE : FALSE;
                                break;
                        case 'mail':
                                $return = \TYPO3\CMS\Core\Utility\GeneralUtility::validEmail($val) ? $val : '';
                                break;
                        case 'ssl':
                                $return = ($val ? 's' : '');
                                break;
                        case 'zip':
                                $return = SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | 5;
                                break;
                        default:
                                $return = $val;
                                break;
                }
                $this->getHook('postValue', array('returnValue' => $return, 'confType' => $conf));
                return $return;
        }

        public function __construct($confOverride = NULL) {
                $this->init($confOverride);
                try {
                        $this->client = new SoapClient(($this->wsdl ? $this->finalURI . '?WSDL' : NULL), $this->soapConf);
                } catch (Exception $e) {
                        $this->error = htmlspecialchars($e->getMessage(), ENT_QUOTES);
                        if ($this->error != '')
                                $this->sendMail(array('subject' => 'Constructor error', 'body' => '<h2>Debug</h2><pre>' . $this->error . '</pre>'));
                }
                if (is_object($this->client) && $this->showfunc) {
                        $this->listFunc = '<pre>';
                        $this->listFunc.= "Function list :\n";
                        $this->listFunc.= print_r($this->client->__getFunctions(), 1);
                        $this->listFunc.= '</pre>';
                }
        }

        public function __destruct() {
                unset($this->client);
        }

        public function getClient() {
                return $this->client;
        }

        public function __call($method, $arguments) {
                $func = create_function('$a,$b,$c', '
		$client = $a->getClient();
		if ($c) {
			try {
				$return = $client->' . $method . '($b);
			} catch (Exception $e) {
				$a->error = htmlspecialchars($e->getMessage(), ENT_QUOTES);
				$return = FALSE;
			}
		} else {
			$return = $client->' . $method . '($b);
			if (!$return->' . $method . 'Result) {
				$a->error = htmlspecialchars($return->getMessage(), ENT_QUOTES);
				$return = FALSE;
			}
		}
		if ($a->error != \'\')
			$a->sendMail(array(\'subject\' => \'Method error: ' . $method . '\', \'body\' => \'<h2>Debug</h2><pre>\'.$a->error.\'</pre>\'));
		return $return;
		');
                $return = $func($this, (is_array($arguments[0]) ? $arguments[0] : implode(',', $arguments)), $this->soapConf['exceptions']);
                if ($this->soapConf['trace'])
                        $this->lastRR = $this->SOAP_RR_debug();
                return $return;
        }

        protected function SOAP_RR_debug() {
                $return = '<pre>';
                $return.= "Request :\n" . htmlspecialchars($this->client->__getLastRequest()) . "\n";
                $return.= "Response:\n" . htmlspecialchars($this->client->__getLastResponse()) . "\n\n";
                $return.= '</pre>';
                return $return;
        }

        public function sendMail($param = array()) {
                if ($this->error_mail != '') { // send mail only if we have recipient
                        $recipient = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->error_mail);
                        $bindcopie = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->bindcopie);
                        $returnPath = $recipient[0];
                        if (count($param > 0) && isset($param['body'])) {
                                $mail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
                                foreach ($recipient as $to_email)
                                        $mail->addTo($to_email, NULL);
                                if ($this->bindcopie != '')
                                        foreach ($bindcopie as $bcc_email)
                                                $mail->addBcc($bcc_email, NULL);
                                if ($returnPath != '')
                                        $mail->setReturnPath($returnPath);
                                if (isset($param['subject']))
                                        $mail->setSubject($param['subject']);
                                $mail->setBody($param['body'], 'text/html');
                                $sent = $mail->send();
                                $fail = $mail->getFailedRecipients();
                                return array('sent' => $sent, 'fail' => $fail);
                        } else
                                return FALSE;
                }
        }

        private function getHook($hookName, $hookConf = array()) {
                // new recommanded coding guideline
                if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['arc_soapapi'][$hookName])) {
                        $hookConf['parentObj'] = &$this->caller;
                        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['arc_soapapi'][$hookName] as $key => $classRef) {
                                $_procObj = \TYPO3\CMS\Core\Utility\GeneralUtility::getUserObj($classRef);
                                $_procObj->$hookName($hookConf, $this);
                        }
                        return TRUE;
                }
                // old method still available for compat. issu
                if (is_array($TYPO3_CONF_VARS['SC_OPTIONS']['arc_soapapi/class.tx_arcsoapapi.php'][$hookName])) {
                        $funcConf['parentObj'] = &$this->caller;
                        foreach ($TYPO3_CONF_VARS['SC_OPTIONS']['arc_soapapi/class.tx_arcsoapapi.php'][$hookName] as $funcRef)
                                \TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction($funcRef, &$hookConf, $this);
                        return TRUE;
                } else
                        return FALSE;
        }

}

?>