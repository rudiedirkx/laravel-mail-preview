<?php

namespace Themsaid\MailPreview;

use Swift_Mime_Message;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Mail\Transport\Transport;

class PreviewTransport extends Transport
{
    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Get the preview path.
     *
     * @var string
     */
    protected $previewPath;

    /**
     * Time in seconds to keep old previews.
     *
     * @var int
     */
    private $lifeTime;

    /**
     * Create a new preview transport instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  string $previewPath
     *
     * @return void
     */
    public function __construct(Filesystem $files, $previewPath, $lifeTime = 60)
    {
        $this->files = $files;
        $this->previewPath = $previewPath;
        $this->lifeTime = $lifeTime;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $this->createEmailPreviewDirectory();

        $this->cleanOldPreviews();

        $this->files->put(
            $this->getEmailPreviewPath($message),
            $this->getEmailPreviewContent($message)
        );
    }

    /**
     * Get the path to the email preview file.
     *
     * @param  \Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getEmailPreviewPath(Swift_Mime_Message $message)
    {
        $to = str_replace(['@', '.'], ['_at_', '_'], array_keys($message->getTo())[0]);

        $subject = $message->getSubject();

        return $this->previewPath.'/'.str_slug($message->getDate().'_'.$to.'_'.$subject, '_').'.html';
    }

    /**
     * Get the content of the email preview file.
     *
     * @param  \Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getEmailPreviewContent(Swift_Mime_Message $message)
    {
        $messageInfo = $this->getMessageInfo($message);

        return $messageInfo.$message->getBody();
    }

    /**
     * Generate a human readable HTML comment with message info.
     *
     * @param \Swift_Mime_Message $message
     *
     * @return string
     */
    private function getMessageInfo(Swift_Mime_Message $message)
    {
        return sprintf(
            "<!--\nFrom:%s, \nto:%s, \nreply-to:%s, \ncc:%s, \nbcc:%s, \nsubject:%s\n-->\n",
            json_encode($message->getFrom()),
            json_encode($message->getTo()),
            json_encode($message->getReplyTo()),
            json_encode($message->getCc()),
            json_encode($message->getBcc()),
            $message->getSubject()
        );
    }

    /**
     * Create the preview directory if necessary.
     *
     * @return void
     */
    protected function createEmailPreviewDirectory()
    {
        if (! $this->files->exists($this->previewPath)) {
            $this->files->makeDirectory($this->previewPath);

            $this->files->put($this->previewPath.'/.gitignore', "*\n!.gitignore");
        }
    }

    /**
     * Delete previews older than the given life time configuration.
     *
     * @return void
     */
    private function cleanOldPreviews()
    {
        $oldPreviews = array_filter($this->files->files($this->previewPath), function ($file) {
            return time() - $this->files->lastModified($file) > $this->lifeTime;
        });

        if ($oldPreviews) {
            $this->files->delete($oldPreviews);
        }
    }
}