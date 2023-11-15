<?php

namespace TechData\AS2SecureBundle\Services;

use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use TechData\AS2SecureBundle\Events\IncomingAs2Request;
use TechData\AS2SecureBundle\Events\Log;
use TechData\AS2SecureBundle\Events\MdnReceived;
use TechData\AS2SecureBundle\Events\MessageReceived;
use TechData\AS2SecureBundle\Events\MessageSent;
use TechData\AS2SecureBundle\Events\OutgoingMessage;
use TechData\AS2SecureBundle\Factories\Adapter as AdapterFactory;
use TechData\AS2SecureBundle\Factories\Message as MessageFactory;
use TechData\AS2SecureBundle\Factories\Partner as PartnerFactory;
use TechData\AS2SecureBundle\Factories\Request as RequestFactory;
use TechData\AS2SecureBundle\Interfaces\MessageSender;
use TechData\AS2SecureBundle\Models\AS2Exception;
use TechData\AS2SecureBundle\Models\Client;
use TechData\AS2SecureBundle\Models\Header;
use TechData\AS2SecureBundle\Models\MDN;
use TechData\AS2SecureBundle\Models\Request as RequestModel;
use TechData\AS2SecureBundle\Models\Server;

class AS2 implements MessageSender
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher, private readonly Server $as2Server, private readonly RequestFactory $requestFactory, private readonly PartnerFactory $partnerFactory, private readonly MessageFactory $messageFactory, private readonly AdapterFactory $adapterFactory, private readonly Client $client)
    {
    }

    /**
     * @throws AS2Exception
     */
    public function handleRequest(Request $request)
    {
        // Convert the symfony request to a as2s request
        $as2Request = $this->requestToAS2Request($request);

        $this->eventDispatcher->dispatch(
            new Log(Log::TYPE_INFO, sprintf('Received AS2 request with message ID: %s', $as2Request->getMessageId()))
        );

        $this->eventDispatcher->dispatch(
            new IncomingAs2Request($as2Request)
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
        } catch (\Exception) {
            // there was an exception while attemptiong to decode, so the message was probably not encoded... ignore the exception
        }
        $files = $response_object->getFiles();
        foreach ($files as $file) {
            // We have an incoming message.  Lets fire the event for it.
            $event = (new MessageReceived())
                ->setMessageId($as2Request->getMessageId())
                ->setMessage(file_get_contents($file['path']))
                ->setSendingPartnerId($partner->id)
                ->setReceivingPartnerId($as2Response->getPartnerTo()->id);

            $this->eventDispatcher->dispatch($event);
        }
    }

    private function requestToAS2Request(Request $request): RequestModel
    {
        $flattenedHeaders = [];
        foreach ($request->headers as $key => $header) {
            $flattenedHeaders[$key] = reset($header);
        }

        return $this->requestFactory->build($request->getContent(), new Header($flattenedHeaders));
    }

    /**
     * @param null $messageSubject
     * @param null $filename
     *
     * @return array
     *
     * @throws \Exception
     * @throws AS2Exception
     */
    public function sendMessage($toPartner, $fromPartner, $messageContent, $messageSubject = null, $filename = null)
    {
        // process request to build outbound AS2 message to VAR

        // initialize outbound AS2Message object
        $message = $this->messageFactory->build(false, [
            'partner_from' => $fromPartner,
            'partner_to' => $toPartner,
            'message_subject' => $messageSubject,
        ]);

        // initialize AS2Adapter for public key encryption between StreamOne and the receiving VAR
        $adapter = $this->adapterFactory->build($fromPartner, $toPartner);

        // write the EDI message that will be sent to a temp file, then use the AS2 adapter to encrypt it
        $tmp_file = $adapter->getTempFilename();
        file_put_contents($tmp_file, $messageContent);
        $message->addFile($tmp_file, 'application/edi-x12', $filename);
        $message->encode();

        $this->eventDispatcher->dispatch(
            new OutgoingMessage($message, $messageContent)
        );

        $result = $this->client->sendRequest($message);
        $this->eventDispatcher->dispatch(
            new MessageSent($message, $result['headers'])
        );

        $response = $result['response'];
        if ($response instanceof MDN) {
            $this->eventDispatcher->dispatch(new MdnReceived($response));
        }

        return $result;
    }
}
