<?php

namespace App\Enums;

/** What a member can do in a project. */
enum ProjectRole: string
{
    /** Everything, including deleting the project and managing members. */
    case Owner = 'owner';
    /** Push keys, API keys, members (except owners) and messages. */
    case Admin = 'admin';
    /** Sends messages, manages segments, templates and subscribers. */
    case Editor = 'editor';
    /** Reads messages, statistics and subscribers. */
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Editor => __('Editor'),
            self::Viewer => __('Viewer'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => __('Full control, including deleting the project.'),
            self::Admin => __('Configures push keys, API keys and members.'),
            self::Editor => __('Sends messages, manages segments and subscribers.'),
            self::Viewer => __('Reads messages, statistics and subscribers.'),
        };
    }

    public function canSend(): bool
    {
        return $this !== self::Viewer;
    }

    public function canConfigure(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
