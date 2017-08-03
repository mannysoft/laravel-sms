<?php

namespace Mannysoft\SMS\Drivers;

use GuzzleHttp\Client;
use Mannysoft\SMS\OutgoingMessage;
use Mannysoft\SMS\MakesRequests;

class VoxboneSMS extends AbstractSMS implements DriverInterface
{
    use MakesRequests;
    
    protected $apiBase = 'https://sms.voxbone.com:4443';
    
    protected $client;
    
    protected $voxbone;

    /**
     * The username.
     */
    protected $username;
    
    /**
     * The password.
     */
    protected $password;

    /**
     * Constructs the VoxboneSMS object.
     *
     * @param $username
     * @param $password
     */
    public function __construct($username, $password)
    {
        $this->client = new Client(['base_uri' => 'https://sms.voxbone.com:4443']);;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Sends a SMS message.
     *
     * @param \Mannysoft\SMS\OutgoingMessage $message
     */
    public function send(OutgoingMessage $message)
    {
        foreach ($message->getTo() as $to) {
            $this->client->request('POST', '/sms/v1/' . $to, [
                'auth' => [$this->username, $this->password, 'digest'],
                'json' => ['from' => $message->getFrom(), 'msg' => $message->composeMessage(), 'frag' => null],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'     => 'application/json',
                ]
            ]);
        }

        return $this->client;
    }

    /**
     * Checks if the transaction has an error.
     *
     * @param $body
     *
     * @return bool
     */
    protected function hasError($body)
    {
        if ($this->hasAResponseMessage($body) && $this->hasProperty($this->getFirstMessage($body), 'status')) {
            $firstMessage = $this->getFirstMessage($body);

            return (int) $firstMessage['status'] !== 0;
        }

        return false;
    }

    /**
     * Log the error message which ocurred.
     *
     * @param $body
     */
    protected function handleError($body)
    {
        $firstMessage = $this->getFirstMessage($body);
        $error = 'An error occurred. Nexmo status code: '.$firstMessage['status'];
        if ($this->hasProperty($firstMessage, 'error-text')) {
            $error = $firstMessage['error-text'];
        }

        $this->throwNotSentException($error, $firstMessage['status']);
    }

    /**
     * Check for a message in the response from Nexmo.
     *
     * @param $body
     */
    protected function hasAResponseMessage($body)
    {
        return
            is_array($body) &&
            array_key_exists('messages', $body) &&
            array_key_exists(0, $body['messages']);
    }

    /**
     * Get the first message in the response from Nexmo.
     *
     * @param $body
     */
    protected function getFirstMessage($body)
    {
        return $body['messages'][0];
    }

    /**
     * Check if the message from Nexmo has a given property.
     *
     * @param $message
     * @param $property
     *
     * @return bool
     */
    protected function hasProperty($message, $property)
    {
        return array_key_exists($property, $message);
    }

    /**
     * Creates many IncomingMessage objects and sets all of the properties.
     *
     * @param $rawMessage
     *
     * @return mixed
     */
    protected function processReceive($rawMessage)
    {
        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($rawMessage);
        $incomingMessage->setFrom((string) $rawMessage->from);
        $incomingMessage->setMessage((string) $rawMessage->body);
        $incomingMessage->setId((string) $rawMessage->{'message-id'});
        $incomingMessage->setTo((string) $rawMessage->to);

        return $incomingMessage;
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
        $this->buildCall('/search/messages/'.$this->apiKey.'/'.$this->apiSecret);

        $this->buildBody($options);

        $rawMessages = json_decode($this->getRequest()->getBody()->getContents());

        return $this->makeMessages($rawMessages->items);
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
        $this->buildCall('/search/message/'.$this->apiKey.'/'.$this->apiSecret.'/'.$messageId);

        return $this->makeMessage(json_decode($this->getRequest()->getBody()->getContents()));
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
        $incomingMessage = $this->createIncomingMessage();
        $incomingMessage->setRaw($raw->get());
        $incomingMessage->setMessage($raw->get('text'));
        $incomingMessage->setFrom($raw->get('msisdn'));
        $incomingMessage->setId($raw->get('messageId'));
        $incomingMessage->setTo($raw->get('to'));

        return $incomingMessage;
    }

    private function setEncoding()
    {
        if (env('NEXMO_ENCODING', 'unicode') === 'unicode') {
            $this->apiEnding = ['type' => 'unicode'];
        }
    }
}
