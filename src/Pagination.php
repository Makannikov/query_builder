<?php
namespace Makan\QueryBuilder;

class Pagination
{
    private $currentPage; // Текущая страница
    public $results = 20; // Количество ресультатов на странице
    public $items; // Количество публиркаций
    private $request_uri; // Параметры адресной строки
    private $url; // Ссылка для страниц
    public $start;
    private $qty_pages;
    public $tmpl;


// Получаем номер текущей страницы
    public function __construct($items, $page = 1, $results = false)
    {

        $this->items = (int)$items;
        $this->currentPage = (int)$page;

// Настраиваем количество результатов на странице
        $this->per_page($results);

        $this->qty_pages();
// Формируем url и получаем текущую страницу
        $this->url();
// Вычисление стартовой позиции
        $this->get_start_position();

        $this->init_links();
    }


    public function url()
    {

// Получаем текущиий URL
// Если появились кавычки переводим их в сущьности и ссылка становится битая
        if ($pos = strpos($_SERVER['REQUEST_URI'], '?')) {
            $this->request_uri = htmlspecialchars(substr($_SERVER['REQUEST_URI'], 0, $pos));
        } else
            $this->request_uri = htmlspecialchars($_SERVER['REQUEST_URI']);
//$this->request_uri = urldecode(htmlspecialchars($_SERVER['REQUEST_URI']));
        $pos = strpos($this->request_uri, '/' . urlencode(PAGINATION_KEY_ACTION));
        if ($pos > 0) {
            $this->url = mb_substr($this->request_uri, 0, $pos, 'UTF-8');
        } else {
            $this->url = $this->request_uri;
        }

        if (!$this->currentPage or $this->currentPage < 0) {
            $this->currentPage = 1;
        }

        ($this->currentPage > $this->qty_pages and $this->qty_pages != 0) and Tools::page_404();

    }

    public function limit()
    {
        return $this->results;
    }

    public function offset()
    {
        return $this->start;
    }

    public function getStarFinish()
    {
        $arr['start'] = $this->start;
        $arr['limit'] = $this->results;
        return $arr;
    }


    private function get_start_position()
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

    private function qty_pages()
    {
// Округляем в большую сторону
        $this->qty_pages = ceil($this->items / $this->results);
//echo '<pre>$this->items ', print_r($this->items, true), '</pre>';
//echo '<pre>$this->results ', print_r($this->results, true), '</pre>';
//echo '<pre>$this->qty_pages ', print_r($this->qty_pages, true), '</pre>';
//exit(PHP_EOL . __FILE__ . '::' . __LINE__ . PHP_EOL);
    }


    public function init_links()
    {

        $this->tmpl = array();

        $this->tmpl['current'] = $this->currentPage;
        $this->tmpl ['results'] = $this->results;
        $this->tmpl['url'] = $this->url;
        $this->tmpl['qty_pages'] = $this->qty_pages;
        $this->tmpl['after'] = '';
        if ($_SERVER['REDIRECT_QUERY_STRING']) $this->tmpl['after'] = '?' . $_SERVER['REDIRECT_QUERY_STRING'];


// Проверяем нужны ли стрелки назад
        if ($this->currentPage != 1) $this->tmpl['prev'] = $this->currentPage - 1;
// Проверяем нужны ли стрелки вперед
        if ($this->currentPage < $this->qty_pages) $this->tmpl['next'] = $this->currentPage + 1;


        if (PAGINTATION_LINKS) {
// Генерируем номера следующих страниц
            for ($i = 1; $i <= PAGINTATION_LINKS; $i++) {
                if (($this->currentPage + $i) <= $this->qty_pages) $this->tmpl['right'][] = $this->currentPage + $i;
            }

// Генерируем номера предыдущих страниц
            for ($i = PAGINTATION_LINKS; $i >= 1; $i--) {
                if ($this->currentPage - $i >= 1) $this->tmpl['left'][] = $this->currentPage - $i;
            }
        }
    }

    private function per_page($per)
    {
        if ($per) {
            $this->results = (int)$per;
        } else {
            $this->results = DEFAULT_PAGE_RESULTS;
        }
    }
}