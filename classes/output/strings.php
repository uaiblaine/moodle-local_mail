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

namespace local_mail\output;

class strings {

    /**
     * Returns a language string with parameters replaced.
     *
     * @param string $id The string identifier.
     * @param string|object|array $param A string, a number or an object to replace parameters with.
     * @return string The localized string.
     */
    public static function get(string $id, mixed $param = null): string {
        return self::manager()->get_string($id, 'local_mail', $param);
    }

    /**
     * Returns all strings.
     *
     * @return string[] All localized strings.
     */
    public static function get_all(): array {
        return self::manager()->load_component_strings('local_mail', current_language());
    }

    /**
     * Returns the identifiers of all strings.
     *
     * @return string[]
     */
    public static function get_ids(): array {
        return array_keys(self::manager()->load_component_strings('local_mail', 'en'));
    }

    /**
     * Returns multiple strings.
     *
     * @param string[] $ids Identifiers.
     * @return string[] Localized strings indexed by identifier.
     */
    public static function get_many(array $ids): array {
        return array_intersect_key(self::get_all(), array_combine($ids, $ids));
    }

    /**
     * String manager that ignores local_mail language packages from AMOS for Catalan and Spanish.
     *
     * @return \core_string_manager
     */
    private static function manager(): \core_string_manager {
        global $CFG;

        static $instance = null;

        if ($instance == null || PHPUNIT_TEST) {
            $instance = new class($CFG->langotherroot, $CFG->langlocalroot, []) extends \core_string_manager_standard {

                public function load_component_strings($component, $lang, $disablecache = false, $disablelocal = false) {
                    global $CFG;

                    if ($component == 'local_mail' && ($lang == 'ca' || $lang == 'es')) {
                        // Ignore language packages from AMOS for Catalan and Spanish.

                        $cachekey = $lang . '_' . $component . '_' . $this->get_key_suffix();
                        $cachedstring = $this->cache->get($cachekey);
                        if (!$disablecache && !$disablelocal && $cachedstring !== false) {
                            return $cachedstring;
                        }

                        $string = [];

                        // First load english pack.
                        include("$CFG->dirroot/local/mail/lang/en/local_mail.php");

                        // And then corresponding local english if present.
                        if (!$disablelocal && file_exists("$CFG->langlocalroot/en_local/local_mail.php")) {
                            include("$CFG->langlocalroot/en_local/local_mail.php");
                        }

                        // Legacy location - used by contrib only.
                        include("$CFG->dirroot/local/mail/lang/$lang/local_mail.php");

                        // Local customisations.
                        if (!$disablelocal && file_exists("$CFG->langlocalroot/{$lang}_local/local_mail.php")) {
                            include("$CFG->langlocalroot/{$lang}_local/local_mail.php");
                        }

                        // Save strings to cache.
                        if (!$disablelocal && $cachedstring === false) {
                            $this->cache->set($cachekey, $string);
                        }

                        return $string;
                    }

                    return parent::load_component_strings($component, $lang, $disablecache, $disablelocal);
                }
            };
        }

        return $instance;
    }
}
