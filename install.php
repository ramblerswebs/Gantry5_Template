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
use Gantry\Framework\ThemeInstaller;
use Gantry5\Loader;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

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

                    // backup each file
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

        //$installer = new ThemeInstaller($parent);
        //$installer->initialize();

        // Install sample data on first install.
        if (in_array($type, array('update'))) {
            try {
                // Setup the file details
                $this->initialise_files();

                // backup each file
                foreach ($this->templatefiles as $file)
                {
                    $file->load();
                    $file->compare();
                    $file->write();
                    $file->tidy();
                }

                //echo $installer->render('install.html.twig');
                echo "Hello";

            } catch (Exception $e) {
                $app = Factory::getApplication();
                $app->enqueueMessage(Text::sprintf($e->getMessage()), 'error');
            }
        } else {
            //echo $installer->render('update.html.twig');
            echo "Hello 2";
        }

        //$installer->finalize();

        return true;
    }

    public function initialise_files()
    {
        $this->templatefiles[0] = new LayoutFile('/layouts/Ramblers.yaml', '/layout.yaml');
        $this->templatefiles[1] = new StyleFile('/gantry/presets.yaml', '/styles.yaml');
    }
}
abstract class TemplateFile
{
    public $original ;
    public $fpbackup ;
    public $fporiginal ;
    public $template ;
    public $customFolder = JPATH_SITE . "/templates/g5_hydrogen/custom";
    public $config_files = array();
    public $orginal_lines = array();
    public $updated_lines = array();


    public function __construct($file_orig, $config_file)
    {
        $this->original = $file_orig;
        $this->fpbackup = $this->customFolder . $this->original . ".backup";
        $this->fporiginal = $this->customFolder . $this->original ;
        // Get the Template Id's to verify
        $Ids = $this->getTemplateIds();
        foreach ($Ids as $id)
        {
            $this->config_files[$id->id] = $this->customFolder . "/config/" . $id->id . $config_file ;
        }
    }
    
    public function backup() {
        // Now move the files.
        copy ($this->fporiginal, $this->fpbackup);
    }

    public function archive() {
        // Archives the backup file based on the date/time
    }

    public function load() {
        // Loads the files into memory so that we can process the files.
        // The old, new and template files will be loaded
        $this->original_lines = file($this->fpbackup);
        $this->updated_lines = file($this->fporiginal);
    }

    public function write() {
        // Write the updated file back out to the system.
        // This should be with any updates

    }
    public function tidy() {
        //$date = date('Ymdhis', time());

        // Remove any files no longer wanted.
        unlink($this->fpbackup);
    }

    private function getTemplateIds()
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

    abstract function compare() ;
        // This function compares the old and new files which have been loaded into memory.
    

}
class LayoutFile extends TemplateFile
{
    public function compare() {

    }
}

class StyleFile extends TemplateFile
{
    public function compare() {

    }
    public function load() {
        parent::load();
    }
}
