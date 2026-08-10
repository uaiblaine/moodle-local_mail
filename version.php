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

/*
 * SPDX-FileCopyrightText: 2012-2014 Institut Obert de Catalunya <https://ioc.gencat.cat>
 * SPDX-FileCopyrightText: 2014-2023 Marc Català <reskit@gmail.com>
 * SPDX-FileCopyrightText: 2017-2026 Albert Gasset <albertgasset@fsfe.org>
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Version number, core requirements and release name of the plugin.
 *
 * @package    local_mail
 * @copyright  2012-2026 Institut Obert de Catalunya, Marc Català, Albert Gasset, Proyecto UNIMOODLE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026081003;
$plugin->requires = 2022112800;
$plugin->supported = [405, 502];
$plugin->component = 'local_mail';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '2.17.3';
