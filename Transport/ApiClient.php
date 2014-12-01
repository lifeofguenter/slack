<?php

/*
 * This file is part of the CLSlackBundle.
 *
 * (c) Cas Leentfaar <info@casleentfaar.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CL\Slack\Transport;

use CL\Slack\Exception\SlackException;
use CL\Slack\Payload\PayloadInterface;
use CL\Slack\Payload\PayloadResponseInterface;
use CL\Slack\Transport\Events\AfterEvent;
use CL\Slack\Transport\Events\BeforeEvent;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Post\PostBody;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ApiClient
{
    /**
     * The (base) URL used for all communication with the Slack API.
     */
    const API_BASE_URL = 'https://slack.com/api/';

    /**
     * @var string|null
     */
    private $token;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param SerializerInterface           $serializer
     * @param ClientInterface|null          $httpClient
     * @param EventDispatcherInterface|null $dispatcher
     * @param string|null                   $token
     */
    public function __construct(
        SerializerInterface $serializer,
        ClientInterface $httpClient = null,
        EventDispatcherInterface $dispatcher = null,
        $token = null
    ) {
        $this->serializer      = $serializer;
        $this->httpClient      = $httpClient ?: new Client();
        $this->eventDispatcher = $dispatcher ?: new EventDispatcher();
        $this->token           = $token;
    }

    /**
     * @param PayloadInterface|array $payload The payload to send
     * @param string|null            $token   Optional token to use during the API-call,
     *                                        defaults to the one configured during construction
     *
     * @throws SlackException If the payload could not be sent
     *
     * @return PayloadResponseInterface Actual class depends on the payload used,
     *                                  e.g. chat.postMessage will return an instance of ChatPostMessagePayloadResponse
     */
    public function send($payload, $method = null, $token = null)
    {
        try {

            if ($token === null && $this->token === null) {
                throw new \InvalidArgumentException('You must supply a token to send a payload (you did not provide one during construction)');
            }

            if (!is_array($payload) && !($payload instanceof PayloadInterface)) {
                throw new \InvalidArgumentException('The payload must either be an array or an object implementing PayloadInterface');
            }

            $originalPayload = $payload;

            if (!is_array($originalPayload)) {
                $method        = $payload->getMethod();
                $payload       = $this->serializePayload($payload);
            }

            $responseData = $this->sendRaw($method, $payload, $token);

            if (!is_array($originalPayload)) {
                return $this->deserializeResponse($responseData, $originalPayload->getResponseClass());
            }

            return $responseData;
        } catch (\Exception $e) {
            throw new SlackException('Failed to send payload to the Slack API', null, $e);
        }
    }

    /**
     * @param string $method
     * @param array  $data
     * @param null   $token
     *
     * @throws SlackException
     *
     * @return array
     */
    private function sendRaw($method, array $data, $token = null)
    {
        try {
            if ($token === null && $this->token === null) {
                throw new \LogicException('You must supply a token to send a payload if you did not provide one during construction');
            }

            $request = $this->createRequest($method, $data, $token);

            $this->eventDispatcher->dispatch(ApiClientEvents::EVENT_BEFORE, new BeforeEvent($data));

            /** @var ResponseInterface $response */
            $response = $this->httpClient->send($request);
        } catch (\Exception $e) {
            throw new SlackException('Failed to send raw payload to the Slack API', null, $e);
        }

        try {
            $responseData = json_decode($response->getBody()->getContents(), true);
            if (!is_array($responseData)) {
                throw new \Exception(sprintf('Expected response data to be of type "array", got "%s"', gettype($responseData)));
            }

            $this->eventDispatcher->dispatch(ApiClientEvents::EVENT_AFTER, new AfterEvent($responseData));
        } catch (\Exception $e) {
            throw new SlackException('Failed to process response from the Slack API', null, $e);
        }

        return $responseData;
    }

    /**
     * @param string $responseContent
     * @param string $responseClass
     *
     * @throws SlackException
     *
     * @return PayloadResponseInterface
     */
    private function deserializeResponse($responseContent, $responseClass)
    {
        $deserializedResponse = $this->serializer->deserialize($responseContent, $responseClass, 'json');

        if (!is_object($deserializedResponse)) {
            throw new SlackException('The response could not be deserialized into an object');
        }

        if (!($deserializedResponse instanceof $responseClass)) {
            throw new SlackException(sprintf(
                'The response could not be deserialized into a payload response object (%s is not an instance of %s)',
                get_class($deserializedResponse),
                $responseClass
            ));
        }

        return $deserializedResponse;
    }

    /**
     * @param string   $event
     * @param callable $callable
     */
    public function addListener($event, $callable)
    {
        $this->eventDispatcher->addListener($event, $callable);
    }

    /**
     * @param string      $method
     * @param array       $payload
     * @param string      $requestMethod
     * @param string|null $token
     *
     * @return RequestInterface
     */
    private function createRequest($method, array $payload, $requestMethod = 'GET', $token = null)
    {
        $payload['token'] = $token ?: $this->token;

        if ($requestMethod !== 'GET') {
            $request = $this->httpClient->createRequest('POST');
            $request->setUrl(self::API_BASE_URL . $method);

            $body = new PostBody();
            $body->replaceFields($payload);
            $body->setField('token', $token);

            $request->setBody($body);
        } else {
            $request = $this->httpClient->createRequest('GET');
            $request->setUrl(self::API_BASE_URL . $method);
            $request->setQuery($payload);
        }

        return $request;
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return array
     */
    private function serializePayload(PayloadInterface $payload)
    {
        return json_decode($this->serializer->serialize($payload, 'json'), true);
    }
}