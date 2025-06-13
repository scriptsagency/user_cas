<?php

/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\AppInfo;

/**
 * Routes for the user_cas app
 */
return [
  'routes' => [
    [
      'name' => 'settings#saveSettings',
      'url' => '/settings/save',
      'verb' => 'POST',
    ],
    [
      'name' => 'authentication#casLogin', // This will be registered as 'user_cas.authentication.casLogin'
      'url' => '/login',
      'verb' => 'GET'
    ],
    [
      'name' => 'authentication#casLogout', // This will be registered as 'user_cas.authentication.casLogout'
      'url' => '/login',
      'verb' => 'POST'
    ]
  ]
];