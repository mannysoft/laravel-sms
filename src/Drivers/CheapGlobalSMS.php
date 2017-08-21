<?php

namespace Mannysoft\SMS\Drivers;

use SMSApi\Client;
use SMSApi\Api\SmsFactory;
use SMSApi\Exception\SmsapiException;
use Mannysoft\SMS\OutgoingMessage;
use Mannysoft\SMS\SMSNotSentException;
use Mannysoft\SMS\MakesRequests;

class CheapGlobalSMS extends AbstractSMS implements DriverInterface
{
    use MakesRequests;
    
    protected $apiBase = 'http://cheapglobalsms.com';
    
    public $account;
    public $password;
    /**
     * 
     *
     * @param $authId
     * @param $authToken
     */
    public function __construct($account, $password)
    {
       $this->account = $account;
       $this->password = $password;
    }

    /**
     * Sends a SMS message.
     *
     * @param \SimpleSoftwareIO\SMS\OutgoingMessage $message
     */
    public function send(OutgoingMessage $message)
    {
        $from = $message->getFrom();
        $composeMessage = $message->composeMessage();

        //Convert to callfire format.
        $numbers = implode(',', $message->getTo());

        $data = [
            'sender_id' => $from,
            'recipients' => $numbers,
            'message' => $composeMessage,
            'sub_account' => $this->account,
            'sub_account_pass' => $this->password,
            'action' => 'send_sms',
        ];
        
        // http://cheapglobalsms.com/api_v1?sub_account=2140_smsto&sub_account_pass=b8w47p27&action=send_sms&sender_id=smsto&message=Hello%2C+there+will+be+a+meeting+today+by+12+noon.&recipients=6392175023198
        $this->buildCall('/api_v1?');
        $this->buildBody($data);

        $response = $this->getRequest();
        $body = json_decode($response->getBody(), true);
        if ($this->hasError($body)) {
            $this->handleError($body);
        }

        return $response;
    }

    /**
     * Processing the raw information from a request and inputs it into the IncomingMessage object.
     *
     * @param $raw
     */
    protected function processReceive($raw)
    {
        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($raw);
        $incomingMessage->setMessage($raw->resource_uri);
        $incomingMessage->setFrom($raw->message_uuid);
        $incomingMessage->setId($raw->message_uuid);
        $incomingMessage->setTo($raw->to_number);
    }

    /**
     * Checks the server for messages and returns their results.
     *
     * @param array $options
     *
     * @return array
     */
    public function checkMessages(array $options = [])
    {
        $start = array_key_exists('start', $options) ? $options['start'] : 0;
        $end = array_key_exists('end', $options) ? $options['end'] : 25;

        $rawMessages = $this->plivo->get_messages([
            'offset' => $start,
            'limit'  => $end,
        ]);

        $incomingMessages = [];

        foreach ($rawMessages['objects'] as $rawMessage) {
            $incomingMessage = $this->createIncomingMessage();
            $this->processReceive($incomingMessage, $rawMessage);
            $incomingMessages[] = $incomingMessage;
        }

        return $incomingMessages;
    }

    /**
     * Gets a single message by it's ID.
     *
     * @param string|int $messageId
     *
     * @return \SimpleSoftwareIO\SMS\IncomingMessage
     */
    public function getMessage($messageId)
    {
        $rawMessage = $this->plivo->get_message(['record_id' => $messageId]);
        $incomingMessage = $this->createIncomingMessage();
        $this->processReceive($incomingMessage, $rawMessage);

        return $incomingMessage;
    }

    /**
     * Receives an incoming message via REST call.
     *
     * @param mixed $raw
     *
     * @return \SimpleSoftwareIO\SMS\IncomingMessage
     */
    public function receive($raw)
    {
        if ($this->verify) {
            $this->validateRequest();
        }

        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($raw->get());
        $incomingMessage->setMessage($raw->get('resource_uri'));
        $incomingMessage->setFrom($raw->get('from_number'));
        $incomingMessage->setId($raw->get('message_uuid'));
        $incomingMessage->setTo($raw->get('to_number'));

        return $incomingMessage;
    }

    /**
     * Checks if a message is authentic from Plivo.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRequest()
    {
        $data = $_POST;
        $url = $this->url;
        $signature = $_SERVER['X-Plivo-Signature'];
        $authToken = $this->authToken;

        if (!$this->plivo->validate_signature($url, $data, $signature, $authToken)) {
            throw new \InvalidArgumentException('This request was not able to verify it came from Plivo.');
        }

        return true;
    }
}
