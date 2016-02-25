<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.7
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2014 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Parser;

use Twig_Autoloader;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Twig_Lexer;
use Twig_Extension_Profiler;
use Twig_Profiler_Profile;

class View_Twig extends \View
{
	protected static $_parser;
	protected static $_parser_loader;
	protected static $_twig_lexer_conf;
	protected static $_twig_profile;
	protected static $_twig_stopwatch = null;

	public static function _init()
	{
		parent::_init();
		Twig_Autoloader::register();
	}

	protected function process_file($file_override = false)
	{
		$file = $file_override ?: $this->file_name;

		$local_data  = $this->get_data('local');
		$global_data = $this->get_data('global');

		// Extract View name/extension (ex. "template.twig")
		$view_name = pathinfo($file, PATHINFO_BASENAME);

		// Twig Loader
		$views_paths = \Config::get('parser.View_Twig.views_paths', array(APPPATH . 'views'));
		array_unshift($views_paths, pathinfo($file, PATHINFO_DIRNAME));
		static::$_parser_loader = new Twig_Loader_Filesystem($views_paths);

		if ( ! empty($global_data))
		{
			foreach ($global_data as $key => $value)
			{
				static::parser()->addGlobal($key, $value);
			}
		}
		else
		{
			// Init the parser if you have no global data
			static::parser();
		}

		$twig_lexer = new Twig_Lexer(static::$_parser, static::$_twig_lexer_conf);
		static::$_parser->setLexer($twig_lexer);

		try
		{
			return static::parser()->loadTemplate($view_name)->render($local_data);
		}
		catch (\Exception $e)
		{
			// Delete the output buffer & re-throw the exception
			ob_end_clean();
			throw $e;
		}
	}

	public $extension = 'twig';

	/**
	 * Returns the Parser lib object
	 *
	 * @return  Twig_Environment
	 */
	public static function parser()
	{
		if ( ! empty(static::$_parser))
		{
			static::$_parser->setLoader(static::$_parser_loader);
			return static::$_parser;
		}

		// Twig Environment
		$twig_env_conf = \Config::get('parser.View_Twig.environment', array('optimizer' => -1));
		static::$_parser = new Twig_Environment(static::$_parser_loader, $twig_env_conf);

		foreach (\Config::get('parser.View_Twig.extensions') as $ext => $args)
		{
			if(is_array($args))
			{
				$rc = new \ReflectionClass($ext);
				static::$_parser->addExtension($rc->newInstanceArgs($args));
				continue;
			}
			
			static::$_parser->addExtension(new $args());
		}

		// Twig Lexer
		static::$_twig_lexer_conf = \Config::get('parser.View_Twig.delimiters', null);
		if (isset(static::$_twig_lexer_conf))
		{
			isset(static::$_twig_lexer_conf['tag_block'])
				and static::$_twig_lexer_conf['tag_block'] = array_values(static::$_twig_lexer_conf['tag_block']);
			isset(static::$_twig_lexer_conf['tag_comment'])
				and static::$_twig_lexer_conf['tag_comment'] = array_values(static::$_twig_lexer_conf['tag_comment']);
			isset(static::$_twig_lexer_conf['tag_variable'])
				and static::$_twig_lexer_conf['tag_variable'] = array_values(static::$_twig_lexer_conf['tag_variable']);
		}
		
		// Twig profiler
		$profiler = \Config::get('parser.View_Twig.profiler', false);
		
		if($profiler)
		{
			if( ! isset(static::$_twig_profile))
			{
				static::$_twig_profile = new Twig_Profiler_Profile();
				static::$_twig_profile->enter();
			}
			
			static::$_parser->addExtension(new Twig_Extension_Profiler(static::$_twig_profile));
					
			if( ! isset(static::$_twig_stopwatch))
			{
				static::$_twig_stopwatch = new \Symfony\Component\Stopwatch\Stopwatch();
			}
		}
		
		static::$_parser->addExtension(new Twig_Extension_Stopwatch(static::$_twig_stopwatch));

		return static::$_parser;
	}
	
	public static function profile()
	{
		$profiler = \Config::get('parser.View_Twig.profiler', false);
		
		$result =  array();
		
		if( ! $profiler) return $result;
		
		static::$_twig_profile->leave();
		
		$dumper = new Twig_Profiler_Dumper_Html();
		
		$result['node_tree'] = $dumper->dump(static::$_twig_profile);
		
		$result['total'] = static::$_twig_profile->getDuration();
		$result['memory_usage'] = static::$_twig_profile->getMemoryUsage();
		$result['memory_peak_usage'] = static::$_twig_profile->getPeakMemoryUsage();
		$result['stopwatch'] = array();
		
		foreach(static::$_twig_stopwatch->getSections() as $section)
		{
			foreach($section->getEvents() as $name => $event)
			{
				$result['stopwatch'][$name] = $event;
			}
		}
		
		return $result;
	}
}

// end of file twig.php
