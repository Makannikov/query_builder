<?php
namespace Makan\QueryBuilder;

class Pagination
{
    /**
     * @var int $currentPage Текущая страница
     * @var int $results Количество результатов на странице
     */
    private string $url; // Ссылка для страниц
    public int $start;
    private int $totalPages;
    public array $tmpl;

    const PAGINATION_KEY_ACTION = 'page';
    const PAGINATION_LINKS = 2;
    const DEFAULT_PAGE_RESULTS = 15;
    private string $link;


    /**
     * @param int $items Всего строк в базе
     * @param int $currentPage Текущая страница
     * @param int $results Кол-во строк на странице
     */
    public function __construct(
        public int $items,
        private int $currentPage = 1,
        public int $results = self::DEFAULT_PAGE_RESULTS)
    {

        $this->totalPages();
        // Формируем url и получаем текущую страницу
        $this->setUrl();

        //TODO Подумать как грамотно обработать этот кейс
        if ($this->currentPage <= 0) {
            $this->currentPage = 1;
        }
        //return ($this->currentPage > $this->totalPages and $this->totalPages != 0);

        // Вычисление стартовой позиции
        $this->getStartPosition();
        $this->init_links();
    }

    public function getCurrentPage(){
        return $this->currentPage;
    }

    public function getTotalPages(){
        return $this->totalPages;
    }

    public function getUrl($num){

        $page = '';

        if($num != 1)
            $page = (str_contains($this->url, '?') ?  '&' : '?') . self::PAGINATION_KEY_ACTION . '=' . $num;

        return $this->url . $page;
    }


    /**
     * @return void форммируем url с ?page=? и без него
     */
    public function setUrl(): void
    {
        // Получаем текущиий URL
        // Если появились кавычки переводим их в сущьности и ссылка становится битая
        /** Remove page=n from uri string and clean characters */
        $this->url =  htmlspecialchars(preg_replace("/[?,&]?page=\d+/", '', $_SERVER['REQUEST_URI']));
    }


    public function limit()
    {
        return $this->results;
    }

    public function offset()
    {
        return $this->start;
    }


    private function getStartPosition()
    {
        // Вычисляем стартовую позицию
        $this->start = $this->currentPage * $this->results - $this->results;
        // 0 * 3 - 3 = 0
        // 1 * 3 - 3 = 0
        // 2 * 3 - 3 = 3
        // 3 * 3 - 3 = 6
        // 4 * 3 - 3 = 9
        // 5 * 3 - 3 = 12
        // 6 * 3 - 3 = 15
        // 7 * 3 - 3 = 18
        // 8 * 3 - 3 = 21
        // 9 * 3 - 3 = 24
        // 10 * 3 - 3 = 27
    }

    private function totalPages()
    {
        // Округляем в большую сторону
        $this->totalPages = ceil($this->items / $this->results);
    }

    public function right(int $num = self::PAGINATION_LINKS) :array{

        for ($i = 1; $i <= $num; $i++) {
            if (($this->currentPage + $i) <= $this->totalPages) {
                $right[] = $this->currentPage + $i;
            }
        }
        return $right ?? [];
    }

    public function left(int $num = self::PAGINATION_LINKS) :array{

        for ($i = $num; $i >= 1; $i--) {
            if ($this->currentPage - $i >= 1) {
                $left[] = $this->currentPage - $i;
            }
        }
        return $left ?? [];
    }
    public function prev() :int {
        return ($this->currentPage <= 1) ? 0 : $this->currentPage - 1;
    }

    public function next(): int
    {
        return ($this->currentPage < $this->totalPages) ? $this->currentPage + 1 : 0;
    }


    public function init_links()
    {
        //TODO Зачем это нужно?
        if (isset($_SERVER['REDIRECT_QUERY_STRING'])) $this->tmpl['after'] = '?' . $_SERVER['REDIRECT_QUERY_STRING'];

    }
}