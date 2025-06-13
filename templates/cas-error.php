<?php
/**
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */
style('user_cas', 'casError');
?>

<span class="error casError">
    <p>
        <b><?php if($_['errorCode']) { p($_['errorCode']);?>: <?php } ?><?php p($_['errorMessage']); ?></b>
    </p>
    <p>
        <a href="<?php p($_['backUrl']); ?>">
            <button><?php p('Go back to the login page'); ?></button>
        </a>
    </p>
</span>
