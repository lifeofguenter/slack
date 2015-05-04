<?php

/*
 * This file is part of the Slack API library.
 *
 * (c) Cas Leentfaar <info@casleentfaar.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CL\Slack\Payload;

use CL\Slack\Model\User;

/**
 * @author Günter Grodotzki <gunter@grodotzki.co.za>
 */
class RtmStartPayloadResponse extends AbstractPayloadResponse
{
    protected $url;

    protected $self;

    protected $team;

    /**
     * @var User[]
     */
    protected $users;

    protected $channels;

    protected $groups;

    protected $ims;

    protected $bots;

    public function getUrl()
    {
        return $this->url;
    }

    public function getSelf()
    {
        return $this->self;
    }

    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Returns 1 or more users of the team, in no particular order.
     *
     * For deactivated users, deleted will be true.
     * The color property is used in some clients to display a colored username.
     *
     * @return User[]
     */
    public function getUsers()
    {
        return $this->users;
    }

    public function getChannels()
    {
        return $this->channels;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getIms()
    {
        return $this->ims;
    }

    public function getBots()
    {
        return $this->bots;
    }
}
