<?php

namespace BotMan\Drivers\Whatsapp;

use BotMan\Drivers\Whatsapp\Extensions\User;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\DriverInterface;
use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsappChatApiDriver extends HttpDriver
{
    /**
     * @const string
     */
    const DRIVER_NAME = 'WhatsApp';
    /**
     * @var string
     */
    protected $endpoint = 'sendMessage';
    /**
     * @var array
     */
    protected $messages = [];
    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return !is_null($this->payload->get('instanceId'));
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
		
        if (empty($this->messages)) {
            $this->loadMessages();
        }
        return $this->messages;
    }

    /**
     * @return void
     */
    protected function loadMessages()
    {
        if ($this->payload->get('messages') !== null) {
            $messages = collect($this->payload->get('messages'))
                ->filter(function($value) {
                    return !$value['fromMe'];
                })
                ->map(function($value) {
                    $message = new IncomingMessage($value['body'], $value['author'], $value['chatId'], $this->payload);
                    $message->addExtras('userName', $value['senderName']);
                    return $message;
                })->toArray();
        }

        $this->messages = $messages ?? [];
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->config->get('url')) && !empty($this->config->get('token'));
    }
	
	

    /**
     * Retrieve User information.
     * @param IncomingMessage $matchingMessage
     * @return UserInterface
     */
    public function getUser(IncomingMessage $matchingMessage)
    {		
		return new User($matchingMessage->getPayload()->get('messages')[0]['author'], $matchingMessage->getSender(), null, null,
            ['fromMe' => $matchingMessage->getPayload()->get('messages')[0]['fromMe']]);
    }

    /**
     * @param IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
		
	    return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * @param string|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
	    return [
            'chatId' => $matchingMessage->getRecipient(),
            'body' => $message->getText()
        ];
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
												
        $result =  $this->http->post($this->buildApiUrl($this->endpoint), [], $payload);
        //VMA
		if (false == $result->isOk()){
			$result   =  file_get_contents(  $this->buildApiUrl($this->endpoint), false,$options );
			
		}
		return $result;	
			
    }
	
	/**
     * @param string|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
	private function sendPost($payload){
		
		$options = stream_context_create([
                                            'http' => [
                                                'method'  => 'POST',
                                                'header'  => 'Content-type: application/json',
                                                'content' => json_encode($payload)
                                            ],
                                            'ssl' => array(
                                                'verify_peer'       => false,
                                                'verify_peer_name'  => false,
                                                'allow_self_signed' => true
                                            )
        
                               ]);
		$body = json_decode( file_get_contents(  $this->buildApiUrl($this->endpoint), false,$options ) );
        return new Response((string) $body, 200, []);

		
	}

    /**
     * @param Request $request
     * @return void
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        $this->event = Collection::make((array) $this->payload->get('messages'));
        $this->content = $request->getContent();
        $this->config = Collection::make($this->config->get('whatsapp', []));
		
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return void
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters = array_replace_recursive([
            'chatId' => $matchingMessage->getRecipient(),
        ], $parameters);


		
		 $result =  $this->http->post($this->buildApiUrl($endpoint), [], $parameters);
        //VMA
		if (false == $result->isOk()){
			$result =  file_get_contents(  $this->buildApiUrl($this->endpoint), false,$options );
		}
		return $result;
		
    }

    /**
     * @param $endpoint
     * @return string
     */
    protected function buildApiUrl($endpoint)
    {
        return $this->config->get('url') . $endpoint . '?token=' . $this->config->get('token');
    }
}