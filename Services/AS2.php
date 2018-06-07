<?php

namespace TechData\AS2SecureBundle\Services;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use TechData\AS2SecureBundle\Events\IncomingAs2Request;
use TechData\AS2SecureBundle\Events\MessageReceived;
use TechData\AS2SecureBundle\Events\MessageSent;
use TechData\AS2SecureBundle\Events\OutgoingMessage;
use TechData\AS2SecureBundle\Factories\Adapter as AdapterFactory;
use TechData\AS2SecureBundle\Interfaces\Events;
use TechData\AS2SecureBundle\Models\Client;
use TechData\AS2SecureBundle\Factories\Message as MessageFactory;
use TechData\AS2SecureBundle\Factories\Partner as PartnerFactory;
use TechData\AS2SecureBundle\Factories\Request as RequestFactory;
use TechData\AS2SecureBundle\Interfaces\MessageSender;
use TechData\AS2SecureBundle\Models\Header;
use TechData\AS2SecureBundle\Models\Server;

/**
 * Description of AS2
 *
 * @author wpigott
 */
class AS2 implements MessageSender
{

    CONST EVENT_MESSAGE_RECEIVED = 'message_received';

    /**
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     *
     * @var PartnerFactory
     */
    private $partnerFactory;

    /**
     * @var Server
     */
    private $as2Server;

    /**
     * @var RequestFactory
     */
    private $requestFactory;
    /**
     * @var MessageFactory
     */
    private $messageFactory;
    /**
     * @var AdapterFactory
     */
    private $adapterFactory;
    /**
     * @var Client
     */
    private $client;

    public function __construct(EventDispatcherInterface $eventDispatcher, Server $server, RequestFactory $requestFactory,
                                PartnerFactory $partnerFactory, MessageFactory $messageFactory, AdapterFactory $adapterFactory, Client $client)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->as2Server = $server;
        $this->requestFactory = $requestFactory;
        $this->partnerFactory = $partnerFactory;
        $this->messageFactory = $messageFactory;
        $this->adapterFactory = $adapterFactory;
        $this->client = $client;
    }

    public function handleRequest(Request $request)
    {
        // Convert the symfony request to a as2s request
        $as2Request = $this->requestToAS2Request($request);

        $this->eventDispatcher->dispatch(
            IncomingAs2Request::EVENT, new IncomingAs2Request($as2Request)
        );

        // Take the request and lets AS2S handle it
        $as2Response = $this->as2Server->handle($as2Request);

        // Get the partner and verify they are authorized
        $partner = $as2Response->getPartnerFrom();
        // @TODO Authorize the partner.

        // process all EDI-X12 messages contained in the AS2 payload
        $response_object = $as2Response->getObject();
        try {
            // the AS2 payload may be further encoded, try to decode it.
            $response_object->decode();
        } catch (\Exception $e) {
            // there was an exception while attemptiong to decode, so the message was probably not encoded... ignore the exception
        }
        $files = $response_object->getFiles();
        foreach ($files as $file) {
            // We have an incoming message.  Lets fire the event for it.
            $event = (new MessageReceived())
                ->setMessage(file_get_contents($file['path']))
                ->setSendingPartnerId($partner->id)
                ->setReceivingPartnerId($as2Response->getPartnerTo()->id);

            $this->eventDispatcher->dispatch(MessageReceived::EVENT, $event);
        }
    }

    private function requestToAS2Request(Request $request) {
        $flattenedHeaders = array();
        foreach($request->headers as $key => $header) {
            $flattenedHeaders[$key] = reset($header);
        }        
        return $this->requestFactory->build($request->getContent(), new Header($flattenedHeaders));
    }
    
    /**
     * @param $toPartner
     * @param $fromPartner
     * @param $messageContent
     * @throws \Exception
     * @throws \TechData\AS2SecureBundle\Models\AS2Exception
     * @throws \TechData\AS2SecureBundle\Models\Exception
     */
    public function sendMessage($toPartner, $fromPartner, $messageContent)
    {
        // process request to build outbound AS2 message to VAR

        // initialize outbound AS2Message object
        $message = $this->messageFactory->build(false, array(
            'partner_from' => $fromPartner,
            'partner_to' => $toPartner,
        ));

        // initialize AS2Adapter for public key encryption between StreamOne and the receiving VAR
        $adapter = $this->adapterFactory->build($fromPartner, $toPartner);

        // write the EDI message that will be sent to a temp file, then use the AS2 adapter to encrypt it
        $tmp_file = $adapter->getTempFilename();
        file_put_contents($tmp_file, $messageContent);
        $message->addFile($tmp_file, 'application/edi-x12');
        $message->encode();

        $this->eventDispatcher->dispatch(
            OutgoingMessage::EVENT, new OutgoingMessage($message, $messageContent)
        );

        // send AS2 message
        $result = $this->client->sendRequest($message);
        $messageSent = new MessageSent();
        $messageSent->setMessage(print_r($result, true));
        $this->eventDispatcher->dispatch(MessageSent::EVENT, $messageSent);

        return $result;
    }
}

