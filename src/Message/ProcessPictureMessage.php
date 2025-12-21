<?php

namespace App\Message;

readonly class ProcessPictureMessage
{
    public function __construct(
        private int $pictureId
    ) {}

    public function getPictureId(): int
    {
        return $this->pictureId;
    }
}