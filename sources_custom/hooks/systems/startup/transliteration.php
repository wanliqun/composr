<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    transliteration
 */

/**
 * Hook class.
 */
class Hook_startup_transliteration
{
    public function run()
    {
        require_code('transliteration');
    }
}
