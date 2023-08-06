<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\Cops\Pages;

use SebLucas\Cops\Calibre\BookList;
use SebLucas\Cops\Calibre\Rating;

class PageRatingDetail extends Page
{
    public function InitializeContent()
    {
        $this->getEntries();
        $rating = Rating::getRatingById($this->idGet, $this->getDatabaseId());
        $this->idPage = $rating->getEntryId();
        $this->title = $rating->getTitle();
        $this->parentTitle = localize("ratings.title");
        $this->parentUri = $rating->getParentUri();
    }

    public function getEntries()
    {
        $booklist = new BookList($this->request);
        [$this->entryArray, $this->totalNumber] = $booklist->getBooksByRating($this->idGet, $this->n);
        $this->sorted = $booklist->orderBy ?? "sort";
    }
}
