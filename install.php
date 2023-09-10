<?php

/**
 * @package   Gantry 5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2021 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */
defined('_JEXEC') or die;

use Gantry\Framework\Gantry;
use Gantry\Framework\Theme;
use Gantry\Admin\Router;
use Gantry\Component\Layout\Layout as LayoutObject;
use Gantry\Component\Layout\LayoutReader;
use Gantry\Framework\ThemeInstaller;
use Gantry5\Loader;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use RocketTheme\Toolbox\File\YamlFile;


/**
 * Gantry 5 Nucleus installer script.
 */
class tpl_hydrogen_ramblersInstallerScript
{
    /** @var string */
    public $requiredGantryVersion = '5.5';
    public $files = array("/layouts/Ramblers.yaml:theme","/gantry/presets.yaml:style");
    public $templatefiles = array();

    public function preflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        $manifest = $parent->getManifest();
        $name = Text::_($manifest->name);

        // Prevent installation if Gantry 5 isn't enabled or is too old for this template.
        try {
            if (!class_exists('Gantry5\Loader')) {
                throw new RuntimeException(sprintf('Please install Gantry 5 Framework before installing %s template!', $name));
            }

            Loader::setup();

            $gantry = Gantry::instance();

            // We need take a backup of the original configuration files, before they are updated. But only if this is an update
            if (in_array($type, array('update'))) {
                try {
                    // Setup all of the file details
                    $this->initialise_files();

                    // backup each file that we need to see the old version of.
                    foreach ($this->templatefiles as $file)
                    {
                        $file->backup();
                    }

                } catch (Exception $e) {
                    $app = Factory::getApplication();
                    $app->enqueueMessage(Text::sprintf($e->getMessage()), 'error');
                }
            }
    
            if (!method_exists($gantry, 'isCompatible') || !$gantry->isCompatible($this->requiredGantryVersion)) {
                throw new \RuntimeException(sprintf('Please upgrade Gantry 5 Framework to v%s (or later) before installing %s template!', strtoupper($this->requiredGantryVersion), $name));
            }

        } catch (Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::sprintf($e->getMessage()), 'error');

            return false;
        }

        return true;
    }

    /**
     * @param string $type
     * @param object $parent
     * @throws Exception
     */
    public function postflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        // If we are doing an update, then merge updated configuration
        if (in_array($type, array('update'))) {
            try {
                // Setup the file details
                $this->initialise_files();

                // Process each file type in turn
                foreach ($this->templatefiles as $file)
                {
                    $file->load();
                    $file->update();
                    $file->write();
                    $file->compile();
                    //$file->restore();
                }

                //echo $installer->render('install.html.twig');
                echo "Settings have been merged";

            } catch (Exception $e) {
                $app = Factory::getApplication();
                $app->enqueueMessage(Text::sprintf($e->getMessage()), 'error');
                return false;
            }
        } else {
            //echo $installer->render('update.html.twig');
            echo "No settings have been merged";
        }

        return true;
    }

    public function initialise_files()
    {
        // Update the Layout first
        $this->templatefiles[0] = new LayoutFile('/layouts/Ramblers.yaml', '/layout.yaml');
        // Then the appropriate style.
        $this->templatefiles[1] = new StyleFile('/gantry/presets.yaml', '/styles.yaml');
    }
}
abstract class TemplateFile
{
    public $file_name;

    public $masterfile_name;
    public $masterfile;
    public $masterfile_content;

    public $backpfile_name;
    public $backupfile;
    public $backupfile_content;
    const BACKUP_EXT = ".backup";

    public $customFolder = JPATH_SITE . "/templates/g5_hydrogen/custom";

    public $configfiles = array();
    public $configfiles_name = array();
    public $configfiles_value = array();


    public function __construct($file_orig, $config_file)
    {
        $this->file_name = $file_orig;
        $this->masterfile_name = $this->customFolder . $this->file_name ;
        $this->backupfile_name = $this->customFolder . $this->file_name . self::BACKUP_EXT;
        // Get the Template Id's to verify
        $Ids = $this->getTemplateIds();
        foreach ($Ids as $id)
        {
            $this->configfiles_name[$id->id] = $this->customFolder . "/config/" . $id->id . $config_file ;
        }
    }
    
    public function backup() {
        // Now move the files.
        copy ($this->masterfile_name, $this->backupfile_name);

        // backup each of the config files. 
        foreach($this->configfiles_name as $file)
        {
            // Backup each config file
            copy ($file, $file . self::BACKUP_EXT);
        }
    }

    public function archive() {
        // Archives the backup file based on the date/time
        $date = date('Ymdhis', time());
        // Rename the backup file name using the date/time.
        rename($this->backupfile_name, $this->masterfile_name . "." . $date);
        // now we need to deal with each config file
        foreach($this->configfiles_name as $file)
        {
            // Backup each config file
            rename ($file . self::BACKUP_EXT, $file . "." . $date);
        }
    }

    public function load() {
        // Loads the files into memory so that we can process the files.
        // The old, new and template files will be loaded
        $this->backupfile = YamlFile::instance($this->backupfile_name);
        $this->backupfile_content = $this->backupfile->content();
        $this->backupfile->free();

        $this->masterfile = YamlFile::instance($this->masterfile_name);
        $this->masterfile_content = $this->masterfile->content();
        $this->masterfile->free();

        // Load each of the configured values 
        foreach($this->configfiles_name as $key => $file)
        {
            // Backup each config file
            $this->configfiles[$key] = YamlFile::instance($file);
            $this->configfiles_value[$key] = $this->configfiles[$key]->content();
            $this->configfiles[$key]->free();
        }

    }

    public function write() {
        // Write the updated file back out to the system.
        // This should be with any updates
        // Note we are only updating the configured values, not the template values.
        foreach($this->configfiles_name as $key => $file)
        {
            // Backup each config file
            //$yfile = YamlFile::instance($file . ".new");
            $yfile = YamlFile::instance($file);
            $yfile->save($this->configfiles_value[$key]);
            $yfile->free();
        }



    }
    public function compile()
    {

    }
    public function restore() {

        // Remove any files no longer wanted.
        unlink($this->masterfile_name);  // Remove the updated file

        // rename the backup to be the original.
        rename($this->backupfile_name, $this->masterfile_name);

        // Re-instate each of the backup config files.
        foreach($this->configfiles_name as $file)
        {
            // Remoove the updated config
            unlink ($file);
            // Move the backup to the original file
            rename ($file . self::BACKUP_EXT, $file);
        }

    }

    public function getTemplateIds()
    {
        // We are updating so fine the template id. 
        $db = JFactory::getDbo(); // Link to the db
        $query = $db->getQuery(true); // Query reference
        // Define the query
        $query->select($db->quoteName('id'));
        $query->from($db->quoteName('#__template_styles'));
        $query->where($db->quoteName('template') . "=" . $db->quote('g5_hydrogen'));

        $db->setQuery($query);
        $results = $db->loadObjectList();

        return $results ;
    }

    abstract function update() ;
        // This function compares the old and new files which have been loaded into memory.
    

}
class LayoutFile extends TemplateFile
{
    public function load()
    {
    }

    public function update() {
        try {
            // Initialise Gantry
            //include "/var/www/html/administrator/components/com_gantry5/gantry5.php";

            if (!defined('GANTRYADMIN_PATH')) {
                define('GANTRYADMIN_PATH', JPATH_COMPONENT_ADMINISTRATOR);
            }

            Loader::setup();

            $gantry = Gantry::instance();
            $gantry['router'] = function ($c) {
                return new Router($c); };

            $router = $gantry['router'];
            $router->boot();
            $router->load();
            
            include "/var/www/html/templates/g5_hydrogen/includes/gantry.php";
        }
        catch (exception $e) {
            $z = 1;
            //throw new \RuntimeException("Failed to Load Gantry files");
        }

        $preset_name = "Ramblers";

        $preset = LayoutObject::preset($preset_name);
        if (!$preset) {
            throw new \RuntimeException('Preset not found', 404);
        }

        $gantry = Gantry::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $gantry['locator'];

        //$oldlayout = LayoutObject::load($outline);
        $outlines = parent::getTemplateIds();
        foreach ($outlines as $outline)
        {
            // Load the current configuration
            $filename = $locator("gantry-config://{$outline->id}/layout.yaml");        // Load the original layout
            $input = LayoutReader::read($filename);        

            // Load the old/backup configuration
            $backup_filename = $locator("gantry-config://{$outline->id}/layout.yaml");        // Load the original layout
            $backup_filename = $backup_filename . parent::BACKUP_EXT;
            $backup_input = LayoutReader::read($backup_filename);        

            // Create the new outline with the new preset
            $layout = new LayoutObject($outline->id, $preset);
            $deleted = isset($input) ? $layout->copySections($input): [];
            //$deleted = isset($input) ? $layout->clearSections()->copySections($input): [];

            // Save layout and its index.
            $layout->save()->saveIndex();
        }
    }
}

class StyleFile extends TemplateFile
{
    public function compile()
    {
        $templateName = "g5_hydrogen"; // This update is based on g5_hydrogen
        $gantry = null;
        if (!class_exists('Gantry5\Loader')) {
            throw new RuntimeException(Text::_('GANTRY5_THEME_INSTALL_GANTRY'));
        }
    
        // Setup Gantry 5 Framework or throw exception.
        Loader::setup();
    
        // Get Gantry instance and return it.
        $gantry = Gantry::instance();
        if (!isset($gantry['theme.name'])) {
            $gantry['theme.path'] = JPATH_ROOT . "/templates/{$templateName}";
            $gantry['theme.name'] = $templateName;
        }

        // Only a single template can be loaded at any time.
        if (!isset($gantry['theme']) && file_exists($gantry['theme.path'] . '/includes/theme.php')) {
            include_once $gantry['theme.path'] . '/includes/theme.php';
        }

        // Get a reference to the theme loaded
        $theme = new Theme($gantry['theme.path'],$gantry['theme.name']);

//      Update each outline in turn. 
        $Ids = $this->getTemplateIds();
        foreach ($Ids as $value)
        {
            $outline = $value->id;
            $theme->updateCss($outline !== 'default' ? [$outline => ucfirst($outline)] : null);
        }
    }

    public function update() {
        // We need to update for each config file
        foreach($this->configfiles_value as $templateid => $configdetail)
        {
            $preset = $configdetail["preset"];
            // First remove any presets which are not within the template file
            $this->remove_presets($templateid, $preset, $configdetail);

            // Now add / update any preset values
            $this->add_update_presets($templateid, $preset, $configdetail);
        }
    }

    function remove_presets($templateid, $preset, $configdetail)
    {
        $items = array();
        // Iterate each value and check to see 
        foreach ($configdetail as $sectionname => $section)
        {
            // if it is an array we need to check the values. 
            if (is_array($section))
            {
                foreach ($section as $key => $value)
                {
                    // Check to see if the key exists in the main file
                    if (!array_key_exists($key, $this->masterfile_content[$preset]['styles'][$sectionname]))
                    {
                        // This does not exist within the master file so it needs to be removed
                        $detail = $templateid . "," . $sectionname . "," . $key;
                        array_push($items, $detail);
                        // Cannot remove while we are iterating, so store and do at the end.
                    }
                }
            }
        }

        // Now iterate the items
        foreach ($items as $value)
        {
            $parts = explode("," , $value);
            $preset = $parts[0];
            $section = $parts[1];
            $key = $parts[2];

            unset($this->configfiles_value[$preset][$section][$key]);
        }
    }

    function add_update_presets($templateid, $preset, $configdetail) {
        // We now know the preset we are working with, So compare the master and the backup file for differences
        // Looking to find where values have been added or removed.
        if (array_key_exists($preset, $this->masterfile_content) && array_key_exists($preset, $this->backupfile_content))
        {
            // So the preset exists in the master and backup file.
            // Now iterate the preset styles (e.g. base, menustyle, navigation)
            foreach($this->masterfile_content[$preset]["styles"] as $stylename => $stylevalues)
            {
                // Check the stylename exist in the backup.
                // E.g. base, menustyle, navigation
                if (array_key_exists($stylename, $this->backupfile_content[$preset]["styles"]))
                {
                    // Style exists in the backup (and the master)
                    // Now check each section within the style
                    foreach ($stylevalues as $key => $value)
                    { 
                        // Check the entry actually exists
                        if (array_key_exists($key, $this->backupfile_content[$preset]["styles"][$stylename]))
                        {
                            // Now check to see if the value needs to be updated
                            $backupvalue = $this->backupfile_content[$preset]["styles"][$stylename][$key];
                            // Only change if it has been updated in the preset.
                            if ($value != $backupvalue)
                            {
                                // This value has been updated, so we need to consider changing it.
                                // But only update if the configured value matches the backup value.
                                if (array_key_exists($key, $this->configfiles_value[$templateid][$stylename]))
                                {
                                    // Value exists, check to see if it has changed.
                                    $configvalue = $this->configfiles_value[$templateid][$stylename][$key];
                                    if ($backupvalue == $configvalue)
                                    {
                                        // Update the config value is this matches the original preset value
                                        $this->configfiles_value[$templateid][$stylename][$key] = $value;
                                    }
                                }
                                else
                                {
                                        // The entry did not exist in the configuration, so we need to add it.
                                        $this->configfiles_value[$templateid][$stylename][$key] = $value;
                                }
                            }
                            else {
                                if (array_key_exists($stylename, $this->configfiles_value[$templateid]))
                                {
                                    if (!array_key_exists($key, $this->configfiles_value[$templateid][$stylename]))
                                    {
                                            // Key does not exist so we need to add it.
                                            $this->configfiles_value[$templateid][$stylename][$key] = $value;
                                    }
                                } 
                                else
                                {
                                    // This is for a new section
                                    $this->configfiles_value[$templateid][$stylename][$key] = $value;
                                }
                            }
                        }
                        else
                        {
                            // The value entry does not exist within this section
                            // So add the value
                            $this->configfiles_value[$templateid][$stylename][$key] = $value;
                        }
                    }
                }
                else
                {
                    // Style section does not exist (this is a new style)
                    // This is a new section, we need to add the whole section
                    foreach ($stylevalues as $key => $value)
                    { 
                        $this->configfiles_value[$templateid][$stylename][$key] = $value;
                    }
                }
            }
        }
        else
        {
            // The chosen preset does not exist in either the master or backp. 
            // So either we have removed the preset they have chosen >> Do Nothing
            // Or they have selected a preset before we made it available >>> How??
        }
    }
}
