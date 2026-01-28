<?php

namespace Maharlika\Broadcasting;

class PrivateChannel extends Channel
{
    /**
     * Get the channel name with private prefix
     */
    public function getName(): string
    {
        return 'private-' . $this->name;
    }
}
