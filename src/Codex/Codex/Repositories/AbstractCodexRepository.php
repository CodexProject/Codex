<?php
namespace Codex\Codex\Repositories;

use ParsedownExtra;
use Codex\Codex\Repositories\Interfaces\CodexRepositoryInterface;
use Illuminate\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem as Files;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractCodexRepository implements CodexRepositoryInterface
{
	/**
	 * @var Illuminate\Config\Repository
	 */
	protected $config;

	/**
	 * @var Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * @var Parsedown
	 */
	protected $parsedown;

	/**
	 * @var string
	 */
	protected $storagePath;

	/**
	 * @var Symfony\Component\Yaml\Yaml
	 */
	protected $yaml;

	/**
	 * Create a new AbstractCodexRepository instance.
	 *
	 * @param  Illuminate\Config\Repository           $config
	 * @param  Illuminate\Filesystem\Filesystem       $files
	 * @param  League\CommonMark\CommonMarkConverter  $commonmark
	 * @param  Symfony\Component\Yaml\Yaml            $yaml
	 */
	public function __construct(Config $config, Files $files, ParsedownExtra $parsedown, Yaml $yaml)
	{
		$this->config      = $config;
		$this->files       = $files;
		$this->parsedown   = $parsedown;
		$this->storagePath = $this->config->get('codex.storage_path');
		$this->yaml        = $yaml;
	}

	/**
	 * Get the default manual.
	 *
	 * @return mixed
	 */
	public function getDefaultManual()
	{
		$manuals = $this->getManuals();

		if (! empty($this->config->get('codex.default_manual'))) {
			return $this->config->get('codex.default_manual');
		} elseif (count($manuals) > 0) {
			return strval($manuals[0]);
		}

		return null;
	}

	/**
	 * Get the default version for the given manual.
	 *
	 * @param  string  $manual
	 * @return string
	 */
	public function getDefaultVersion($manual)
	{
		$versions = $this->getVersions($manual);

		return $versions[0];
	}

	/**
	 * Get all manuals from documentation directory.
	 *
	 * @return array
	 */
	public function getManuals()
	{
		$manuals = $this->getDirectories($this->storagePath);

		return $manuals;
	}

	/**
	 * Get all versions fro the given manual.
	 *
	 * @param  string  $manual
	 * @return array
	 */
	public function getVersions($manual)
	{
		$alpha     = array();
		$numeric   = array();
		$manualDir = "{$this->storagePath}/{$manual}";
		$versions  = $this->getDirectories($manualDir);

		foreach ($versions as $version) {
			if (ctype_alpha(substr($version, 0, 2))) {
				$alpha[] = $version;
			} else {
				$numeric[] = $version;
			}
		}

		sort($alpha);
		rsort($numeric);

		if ($this->config->get('codex.version_ordering') == 'numeric-first') {
			return array_merge($numeric, alpha);
		} else {
			return array_merge($alpha, $numeric);
		}
	}

	/**
	 * Return an array of folders within the supplied path.
	 *
	 * @param  string  $path
	 * @return array
	 */
	protected function getDirectories($path)
	{
		if ($this->files->exists($path) === false) {
			abort(404);
		}

		$directories = $this->files->directories($path);
		$folders     = array();

		if (count($directories) > 0) {
			foreach ($directories as $dir) {
				$dir       = str_replace('\\', '/', $dir);
				$folder    = explode('/', $dir);
				$folders[] = end($folder);
			}
		}

		return $folders;
	}

	/**
	 * Parse Markdown to HTML.
	 *
	 * @param  string  $content
	 * @return string
	 */
	protected function parseMarkdown($content)
	{
		$metadata = $this->parseMetadata($content);

		return $this->parsedown->text($content);
	}

	/**
	 * Parse YAML metadata at the top of files.
	 *
	 * @return text
	 */
	protected function parseMetadata($content)
	{
		$pattern = '/<!---\n([\w\W]*?)\n-->/';

		preg_match($pattern, $content, $matches);

		if (count($matches) > 1) {
			$content = preg_replace($pattern, '', $content);

			return $this->yaml->parse($matches[1]);
		}

		return null;
	}
}
