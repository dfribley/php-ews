<?php
/**
 * Contains OAuthSoapClient.
 */

/**
 * Soap Client using Microsoft's OAuth Authentication.
 *
 * Adapted from the NTLMSoapClient to use OAuth authentication provided by:
 *
 * Copyright (c) 2008 Invest-In-France Agency http://www.invest-in-france.org
 *
 * Author : Thomas Rabaix
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 * @link http://rabaix.net/en/articles/2008/03/13/using-soap-php-with-ntlm-authentication
 * @author Thomas Rabaix
 *
 * @package php-ews\Auth
 */
class OAuthSoapClient extends SoapClient
{
    public static $last_path;

    protected $write_to_file;
    /**
     * cURL resource used to make the SOAP request
     *
     * @var resource
     */
    protected $ch;

    /**
     * Whether or not to validate ssl certificates
     *
     * @var boolean
     */
    protected $validate = false;

    /**
     * Performs a SOAP request
     *
     * @link http://php.net/manual/en/function.soap-soapclient-dorequest.php
     *
     * @param string $request the xml soap request
     * @param string $location the url to request
     * @param string $action the soap action.
     * @param integer $version the soap version
     * @param integer $one_way
     * @return string the xml soap response.
     * @throws \EWS_Exception
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        self::$last_path = null;
        $headers = array(
            'Method: POST',
            'Connection: Keep-Alive',
            'User-Agent: PHP-SOAP-CURL',
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "'.$action.'"',
            'Authorization: Bearer ' . $this->access_token
        );

        $this->__last_request_headers = $headers;
        $this->ch = curl_init($location);

        curl_setopt($this->ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $this->validate);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->validate);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_POST, true );
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);

        if ($this->write_to_file)
        {
            $file_path = '/tmp/transfer_xml_' . md5($action . $this->access_token . time() . getmypid() . rand(0, getmypid())) . '.' . getmypid();
            $file_handler = fopen($file_path, 'w');
            $error_path = $file_path . '_error';
            $file_error_handler = fopen($error_path, 'w');

            curl_setopt($this->ch, CURLOPT_FILE, $file_handler);
            curl_setopt($this->ch, CURLOPT_STDERR, $file_error_handler);

            self::$last_path = $file_path;

            // Return valid xml.
            $xml = '<?xml version="1.0" encoding="utf-8"?><s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Header><h:ServerVersionInfo MajorVersion="15" MinorVersion="1" MajorBuildNumber="497" MinorBuildNumber="14" Version="V2016_04_13" xmlns:h="http://schemas.microsoft.com/exchange/services/2006/types" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"/></s:Header><s:Body><path>'
                . $file_path .
                '</path></s:Body></s:Envelope>';

            curl_exec($this->ch);

            $error_response = preg_replace('/&#x[0-1]?[0-9A-F];/', ' ', file_get_contents($error_path));

            fclose($file_handler);
            fclose($file_error_handler);

            $file_stat = stat($file_path);

            if (!$file_stat || $file_stat['size'] === 0)
            {
                $error_response = 'Empty Response';
            }
        }
        else
        {
            $xml = curl_exec($this->ch);
            $error_response = preg_replace('/&#x[0-1]?[0-9A-F];/', ' ', $xml);
        }

        if (curl_getinfo(CURLINFO_SIZE_DOWNLOAD) === 0)
        {
            $error_response = 'Empty Response';
        }

        // TODO: Add some real error handling.
        // If the response if false than there was an error and we should throw
        // an exception.
        if (!empty($error_response)) {
            throw new EWS_Exception(
                'Curl error: ' . curl_error($this->ch),
                curl_errno($this->ch)
            );
        }
        elseif (isset($error_path))
        {
            unlink($error_path);
        }

        return $xml;
    }

    public function __call($function_name, $arguments)
    {
        $result = parent::__call($function_name, $arguments);

        if ($this->write_to_file)
        {
            return self::$last_path;
        }

        return $result;
    }

    /**
     * Returns last SOAP request headers
     *
     * @link http://php.net/manual/en/function.soap-soapclient-getlastrequestheaders.php
     *
     * @return string the last soap request headers
     */
    public function __getLastRequestHeaders()
    {
        return implode('n', $this->__last_request_headers) . "\n";
    }

    /**
     * Sets whether or not to validate ssl certificates
     *
     * @param boolean $validate
     * @return bool
     */
    public function validateCertificate($validate = true)
    {
        $this->validate = $validate;

        return true;
    }
}
