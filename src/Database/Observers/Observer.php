<?php

namespace Maharlika\Database\Observers;

/**
 * Base Observer class for model events.
 * 
 * Child classes can implement any of the following methods:
 * - retrieved, creating, created, updating, updated
 * - saving, saved, deleting, deleted, restoring, restored, replicating
 */
abstract class Observer
{
    // Empty abstract class - child classes define their own methods
}