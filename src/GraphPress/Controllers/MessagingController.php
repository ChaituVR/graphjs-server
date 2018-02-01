<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphPress\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;
use Pho\Lib\Graph\ID;


/**
 * Takes care of Messaging
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class MessagingController extends AbstractController 
{
    /**
     * Send a Message
     * 
     * [to, message]
     *
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @param string $id
     * 
     * @return void
     */
    public function message(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id=$this->dependOnSession(...\func_get_args())))
            return;
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['to', 'message']);
        if(!$v->validate()) {
            $this->fail($response, "Valid recipient and message are required.");
            return;
        }
        if(!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["to"])) {
            $this->fail($response, "Invalid recipient");
            return;
        }
        if(empty($data["message"])) {
            $this->fail($response, "Message can't be empty");
            return;
        }

        $i = $kernel->gs()->node($id);
        $recipient = $kernel->gs()->node($data["to"]);
        $msg = $i->message($recipient, $data["message"]);
        $this->succeed($response, [
                "id" => (string) $msg->id()
            ]
        );
    }

    /**
     * Fetch Unread Message Count
     *
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @param string $id
     * 
     * @return void
     */
    public function fetchUnreadMessageCount(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id=$this->dependOnSession(...\func_get_args())))
            return;
        $i = $kernel->gs()->node($id);
        $incoming_messages = $i->getIncomingMessages();
        $this->succeed($response, [
                "count" => (string) count($incoming_messages)
            ]
        );
    }

    /**
     * Fetch Inbox
     * 
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @param string $id
     * 
     * @return void
     */
    public function fetchInbox(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id=$this->dependOnSession(...\func_get_args())))
            return;
        $i = $kernel->gs()->node($id);
        $incoming_messages = $i->getIncomingMessages();
        $ret = [];
        foreach($incoming_messages as $m) 
        {
            $ret[(string) $m->id()] = [
                "from" => $m->tail()->id()->toString(),
                "message" => substr($m->getContent(), 0, 70),
                "is_read" => $m->getIsRead() ? true : false
            ];
        }
        $this->succeed($response, [
                "messages" => $ret
            ]
        );
    }

    /**
     * Fetch Message
     * 
     * [msgid]
     *
     * @param Request $request
     * @param Response $response
     * @param Session $session
     * @param Kernel $kernel
     * @param string $id
     * 
     * @return void
     */
    public function fetchMessage(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        if(is_null($id=$this->dependOnSession(...\func_get_args())))
            return;
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['msgid']);
        if(!$v->validate()) {
            $this->fail($response, "Valid message id required.");
            return;
        }
        if(!preg_match("/^[0-9a-fA-F][0-9a-fA-F]{30}[0-9a-fA-F]$/", $data["msgid"])) {
            $this->fail($response, "Invalid message ID");
            return;
        }
        $i = $kernel->gs()->node($id);
        $msgid = ID::fromString($data["msgid"]);
        if( !$i->hasIncomingMessage($msgid) && !$i->hasSentMessage($msgid) ) {
            $this->fail($response, "Message ID is not associated with the logged in user.");
            return;
        }
        $msg = $kernel->gs()->edge($data["msgid"]);
        $msg->setIsRead(true);
        $this->succeed($response, [
                "message" => array_merge(
                    $msg->attributes()->toArray(),
                    [
                        "from" => (string) $msg->tail()->id(),
                        "to" => (string) $msg->head()->id()
                    ]
                )
            ]
        );
    }
}
