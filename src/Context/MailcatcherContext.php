<?php
/**
 * @author    Jonas Eriksson <jonas.eriksson@interactivesolutions.se>
 *
 * @copyright Interactive Solutions
 */
declare(strict_types = 1);

namespace InteractiveSolutions\ZfBehat\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use InteractiveSolutions\ZfBehat\Assertions;
use InteractiveSolutions\ZfBehat\Context\Aware\MailcatcherClientAwareInterface;
use InteractiveSolutions\ZfBehat\Context\Aware\MailcatcherClientAwareTrait;

class MailcatcherContext implements MailcatcherClientAwareInterface, SnippetAcceptingContext
{
    use MailcatcherClientAwareTrait;

    private function cleanMessages()
    {
        $this->getClient()->delete('/messages');
    }

    private function getLastMessage()
    {
        $messages = $this->getMessages();

        Assertions::assertNotEmpty($messages);

        return reset($messages);
    }

    /**
     * @return array
     */
    private function getMessages()
    {
        $jsonResponse = $this->getClient()->get('/messages');
        $jsonString   = $jsonResponse->getBody()->getContents();

        Assertions::assertJson($jsonString);

        return json_decode($jsonString, true);
    }

    /**
     * @param $id
     * @return array
     */
    private function getMessageById($id)
    {
        $response       = $this->getClient()->get(sprintf("/messages/%s.json", $id));
        $responseString = (string) $response->getBody()->getContents();

        Assertions::assertJson($responseString);

        return json_decode($responseString, true);
    }

    /**
     * @Given an empty mailcatcher
     */
    public function anEmptyMailcatcher()
    {
        $this->cleanMessages();
    }

    /**
     * @Then the email should contain :text
     *
     * @param string $text
     */
    public function emailShouldContainsText($text)
    {
        $id             = $this->getLastMessage()['id'];
        $response       = $this->getClient()->get(sprintf("/messages/%s.plain", $id));
        $responseString = (string) $response->getBody()->getContents();

        Assertions::assertContains($text, $responseString);
    }

    /**
     * @Then an email should be sent
     */
    public function emailShouldBeSent()
    {
        $this->getLastMessage();
    }

    /**
     * @Then the receiver should match email :email
     *
     * @param string $email
     */
    public function receiverShouldMatchEmail($email)
    {
        $email   = sprintf('<%s>', $email);
        $message = $this->getMessageById($this->getLastMessage()['id']);

        Assertions::assertContains($email, $message['recipients']);
    }

    /**
     * @Then the sender should match email :email
     *
     * @param string $email
     */
    public function senderShouldMatchEmail($email)
    {
        $email   = sprintf('<%s>', $email);
        $message = $this->getMessageById($this->getLastMessage()['id']);

        Assertions::assertContains($email, $message['sender']);
    }

    /**
     * @Then the subject should contain :subject
     *
     * @param string $subject
     */
    public function subjectShouldContain($subject)
    {
        $message = $this->getLastMessage();

        Assertions::assertContains($subject, $message['subject']);
    }
}
