<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Calibre;

use SebLucas\Cops\Pages\Page;

class Serie extends Base
{
    public const PAGE_ID = Page::ALL_SERIES_ID;
    public const PAGE_ALL = Page::ALL_SERIES;
    public const PAGE_DETAIL = Page::SERIE_DETAIL;
    public const SQL_TABLE = "series";
    public const SQL_COLUMNS = "series.id as id, series.name as name, series.sort as sort, count(*) as count";
    public const SQL_ALL_SERIES = "select {0} from series, books_series_link where series.id = series group by series.id, series.name, series.sort order by series.sort";
    public const SQL_SERIES_FOR_SEARCH = "select {0} from series, books_series_link where series.id = series and upper (series.name) like ? group by series.id, series.name, series.sort order by series.sort";

    public $id;
    public $name;

    public function __construct($post)
    {
        $this->id = $post->id;
        $this->name = $post->name;
    }

    public function getUri()
    {
        return "?page=".self::PAGE_DETAIL."&id=$this->id";
    }

    public function getEntryId()
    {
        return self::PAGE_ID.":".$this->id;
    }

    public static function getCount()
    {
        // str_format (localize("series.alphabetical", count(array))
        return parent::getCountGeneric(self::SQL_TABLE, self::PAGE_ID, self::PAGE_ALL);
    }

    public static function getSerieByBookId($bookId)
    {
        $result = parent::getDb()->prepare('select  series.id as id, name
from books_series_link, series
where series.id = series and book = ?');
        $result->execute([$bookId]);
        if ($post = $result->fetchObject()) {
            return new Serie($post);
        }
        return null;
    }

    public static function getSerieById($serieId)
    {
        $result = parent::getDb()->prepare('select id, name  from series where id = ?');
        $result->execute([$serieId]);
        if ($post = $result->fetchObject()) {
            return new Serie($post);
        }
        return null;
    }

    public static function getAllSeries()
    {
        return Base::getEntryArrayWithBookNumber(self::SQL_ALL_SERIES, self::SQL_COLUMNS, [], self::class);
    }

    public static function getAllSeriesByQuery($query)
    {
        return Base::getEntryArrayWithBookNumber(self::SQL_SERIES_FOR_SEARCH, self::SQL_COLUMNS, ['%' . $query . '%'], self::class);
    }
}