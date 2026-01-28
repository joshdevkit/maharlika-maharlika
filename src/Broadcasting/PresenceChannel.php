<?php

namespace Maharlika\Broadcasting;

class PresenceChannel extends Channel
{
    /**
     * Get the channel name with presence prefix
     */
    public function getName(): string
    {
        return 'presence-' . $this->name;
    }
}
