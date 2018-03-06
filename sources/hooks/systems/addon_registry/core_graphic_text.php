<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_graphic_text
 */

/**
 * Hook class.
 */
class Hook_addon_registry_core_graphic_text
{
    /**
     * Get a list of file permissions to set.
     *
     * @param  boolean $runtime Whether to include wildcards represented runtime-created chmoddable files
     * @return array File permissions to set
     */
    public function get_chmod_array($runtime = false)
    {
        return array();
    }

    /**
     * Get the version of Composr this addon is for.
     *
     * @return float Version number
     */
    public function get_version()
    {
        return cms_version_number();
    }

    /**
     * Get the description of the addon.
     *
     * @return string Description of the addon
     */
    public function get_description()
    {
        return 'Core rendering functionality for imagery.';
    }

    /**
     * Get a list of tutorials that apply to this addon.
     *
     * @return array List of tutorials
     */
    public function get_applicable_tutorials()
    {
        return array(
            'tut_fringe',
        );
    }

    /**
     * Get a mapping of dependency types.
     *
     * @return array File permissions to set
     */
    public function get_dependencies()
    {
        return array(
            'requires' => array(),
            'recommends' => array(),
            'conflicts_with' => array(),
        );
    }

    /**
     * Explicitly say which icon should be used.
     *
     * @return URLPATH Icon
     */
    public function get_default_icon()
    {
        return 'themes/default/images/icons/admin/component.svg';
    }

    /**
     * Get a list of files that belong to this addon.
     *
     * @return array List of files
     */
    public function get_file_list()
    {
        return array(
            'sources/hooks/systems/addon_registry/core_graphic_text.php',
            'themes/default/css/fonts.css',
            'data/fonts/Aerial.ttf',
            'data/fonts/AerialBd.ttf',
            'data/fonts/AerialBdIt.ttf',
            'data/fonts/AerialIt.ttf',
            'data/fonts/AerialMono.ttf',
            'data/fonts/AerialMonoBd.ttf',
            'data/fonts/AerialMonoBdIt.ttf',
            'data/fonts/AerialMonoIt.ttf',
            'data/fonts/FreeMono.ttf',
            'data/fonts/FreeMonoBold.ttf',
            'data/fonts/FreeMonoBoldOblique.ttf',
            'data/fonts/FreeMonoOblique.ttf',
            'data/fonts/index.html',
            'data/fonts/toga.ttf',
            'data/fonts/togabd.ttf',
            'data/fonts/togabi.ttf',
            'data/fonts/togait.ttf',
            'data/fonts/togase.ttf',
            'data/fonts/togasebd.ttf',
            'data/fonts/Tymes.ttf',
            'data/fonts/TymesBd.ttf',
            'data/fonts/Vera.ttf',
            'data/fonts/VeraBd.ttf',
            'data/fonts/VeraBI.ttf',
            'data/fonts/VeraIt.ttf',
            'data/fonts/VeraMoBd.ttf',
            'data/fonts/VeraMoBI.ttf',
            'data/fonts/VeraMoIt.ttf',
            'data/fonts/VeraMono.ttf',
            'data/fonts/Veranda.ttf',
            'data/fonts/VerandaBd.ttf',
            'data/fonts/VerandaBdIt.ttf',
            'data/fonts/VerandaIt.ttf',
            'data/fonts/VeraSe.ttf',
            'data/fonts/VeraSeBd.ttf',
            'data/gd_text.php',
            'data_custom/fonts/index.html',
        );
    }
}
