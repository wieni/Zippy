<?php

/*
 * This file is part of Zippy.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Alchemy\Zippy\Parser;

/**
 * This class is responsible of parsing GNUTar command line output
 */
class ZipOutputParser implements ParserInterface
{
    const LENGTH        = '(\d*)';
    const ISO_DATE      = '([0-9]+-[0-9]+-[0-9]+\s+[0-9]+:[0-9]+)';
    const FILENAME      = '(.*)';

    /**
     * @var string
     */
    private $dateFormat;

    /**
     * @param string $dateFormat
     */
    public function __construct($dateFormat = "Y-m-d H:i")
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * @inheritdoc
     */
    public function parseFileListing($output)
    {
        $lines = array_values(array_filter(explode("\n", $output)));
        $members = array();

        foreach ($lines as $line) {
            $matches = array();

            // 785  2012-10-24 10:39  file
            if (!preg_match_all("#" .
                self::LENGTH . "\s+" . // match (785)
                self::ISO_DATE . "\s+" . // match (2012-10-24 10:39)
                self::FILENAME . // match (file)
                "#",
                $line, $matches, PREG_SET_ORDER
            )) {
                continue;
            }

            $chunks = array_shift($matches);

            if (4 !== count($chunks)) {
                continue;
            }

            $mtime = \DateTime::createFromFormat($this->dateFormat, $chunks[2]);

            if ($mtime === false) {
                // See https://github.com/alchemy-fr/Zippy/issues/111#issuecomment-251668427
                $mtime = \DateTime::createFromFormat('H:i Y-m-d', $chunks[2]);
            }

            if ($mtime === false) {
                // See https://github.com/alchemy-fr/Zippy/issues/111#issuecomment-1051243883
                $mtime = \DateTime::createFromFormat('m-d-Y H:i', $chunks[2]);
            }

            if ($mtime === false) {
                $mtime = new \DateTime($chunks[2]);
            }

            $members[] = array(
                'location'  => $chunks[3],
                'size'      => $chunks[1],
                'mtime'     => $mtime,
                'is_dir'    => '/' === substr($chunks[3], -1)
            );
        }

        return $members;
    }

        /**
         * @inheritdoc
         */
    public function parseInflatorVersion($output)
    {
        $lines = array_values(array_filter(explode("\n", $output, 3)));

        $chunks = explode(' ', $lines[1], 3);

        if (2 > count($chunks)) {
            return null;
        }

        list(, $version) = $chunks;

        return $version;
    }

    /**
     * @inheritdoc
     */
    public function parseDeflatorVersion($output)
    {
        $lines = array_values(array_filter(explode("\n", $output, 2)));
        $firstLine = array_shift($lines);
        $chunks = explode(' ', $firstLine, 3);

        if (2 > count($chunks)) {
            return null;
        }

        list(, $version) = $chunks;

        return $version;
    }
}
