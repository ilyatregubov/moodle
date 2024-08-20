<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace customfield_number\hook;

use core\hook\described_hook;
use customfield_number\provider_base;
use customfield_number\field_controller;

/**
 * Hook for adding custom providers to the provider_base.
 *
 * @package    customfield_number
 * @copyright  2024 Ilya Tregubov <ilya.tregubov@proton.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_custom_providers implements described_hook {
    /**
     * @var field_controller
     */
    protected field_controller $field;

    /**
     * @var array
     */
    protected array $providers = [];

    /**
     * Constructor.
     *
     * @param field_controller $field the custom field controller
     */
    public function __construct(field_controller $field) {
        $this->field = $field;
    }

    /**
     * Describes the hook purpose.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'This hook allows adding custom providers to the provider_base.';
    }

    /**
     * List of tags that describe this hook.
     *
     * @return string[]
     */
    public static function get_hook_tags(): array {
        return ['custom_providers'];
    }

    /**
     * Get custom field controller instance.
     *
     * @return field_controller
     */
    public function get_field(): field_controller {
        return $this->field;
    }

    /**
     * Add a provider to the hook.
     *
     * @param provider_base $provider
     */
    public function add_provider(provider_base $provider): void {
        $this->providers[] = $provider;
    }

    /**
     * Get the list of providers added through the hook.
     *
     * @return object[]
     */
    public function get_providers(): array {
        return $this->providers;
    }
}
