<?php

namespace App\Modules\DeliveryEngine\Rendering;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Immutable output of {@see RenderingPipeline}: everything the mailer needs
 * to assemble the multipart/alternative MIME message (docs/38 §38.2 stage 8).
 */
final class RenderedEmail
{
    /**
     * @param  list<InlineImage>  $inlineImages
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly ?string $text = null,
        public readonly ?string $toName = null,
        public readonly array $inlineImages = [],
    ) {}

    public function withSubject(string $subject): self
    {
        return new self($subject, $this->html, $this->text, $this->toName, $this->inlineImages);
    }

    public function withHtml(string $html): self
    {
        return new self($this->subject, $html, $this->text, $this->toName, $this->inlineImages);
    }

    public function withText(?string $text): self
    {
        return new self($this->subject, $this->html, $text, $this->toName, $this->inlineImages);
    }

    public function withToName(?string $toName): self
    {
        return new self($this->subject, $this->html, $this->text, $toName, $this->inlineImages);
    }

    /**
     * @param  list<InlineImage>  $images
     */
    public function withInlineImages(array $images): self
    {
        return new self($this->subject, $this->html, $this->text, $this->toName, $images);
    }

    public function toAddress(string $email): Address
    {
        return $this->toName !== null && $this->toName !== ''
            ? new Address($email, $this->toName)
            : new Address($email);
    }

    /**
     * Stage 8 — fills To / Subject / HTML / text parts and the inline
     * (`cid:`) related parts on an outbound Symfony message.
     */
    public function applyTo(Email $email, string $toEmail): Email
    {
        $email->to($this->toAddress($toEmail))
            ->subject($this->subject)
            ->html($this->html);

        if ($this->text !== null && $this->text !== '') {
            $email->text($this->text);
        }

        foreach ($this->inlineImages as $image) {
            $email->embed($image->contents, $image->cid, $image->mimeType);
        }

        return $email;
    }
}
