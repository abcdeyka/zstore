<?php

namespace App\Pages\Report;

use App\Application as App;
use App\Entity\Item;
use App\Entity\Stock;
use App\Entity\Store;
use App\Entity\Category;
use App\Helper as H;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\RedirectLink;
use Zippy\Html\Panel;

/**
 * Состояние складов
 */
class StoreItems extends \App\Pages\Base
{
    public function __construct() {
        parent::__construct();

        if (false == \App\ACL::checkShowReport('StoreItems')) {
            return;
        }

        $this->add(new Form('filter'))->onSubmit($this, 'OnSubmit');

        $this->filter->add(new CheckBox('fminus'));
        $this->filter->add(new CheckBox('fmin'));
        $this->filter->add(new CheckBox('fver'));
        $this->filter->add(new CheckBox('fcust'));

        // Показывать все, включая нулевые
        $this->filter->add(new CheckBox('fall'));

        // Категория
        $this->filter->add(
            new DropDownChoice('searchcat', Category::getList(), 0)
        );

        // Поиск по названию / артикулу
        $this->filter->add(new TextInput('searchkey'));

        $this->add(new Panel('detail'))->setVisible(false);

        $this->detail->add(new Label('preview'));

        \App\Session::getSession()->issubmit = false;
    }


    /**
     * Обработка фильтра
     */
    public function OnSubmit($sender) {

        $this->detail->setVisible(true);

        $fver = $this->filter->fver->isChecked();

        $html = $fver
            ? $this->generateReportVer()
            : $this->generateReport();

        \App\Session::getSession()->printform =
            "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>"
            . $html .
            "</body></html>";

        $this->detail->preview->setText($html, true);
    }


    /**
     * Обычный режим
     */
    private function generateReport() {

        $common = \App\System::getOptions('common');

        $fmin     = $this->filter->fmin->isChecked();
        $fminus   = $this->filter->fminus->isChecked();
        $fcust    = $this->filter->fcust->isChecked();
        $fall     = $this->filter->fall->isChecked();

        $fcat = $this->filter->searchcat->getValue();

        // Поиск
        $searchkey = trim($this->filter->searchkey->getText());


        // ---------------------------------------------------------
        // ФИЛЬТР ТОВАРОВ
        // ---------------------------------------------------------

        $where = 'disabled<>1 ';


        // ---------------------------------------------------------
        // ФИЛЬТР ПО КАТЕГОРИИ
        // ---------------------------------------------------------

        if ($fcat > 0) {

            $cat = \App\Entity\Category::load($fcat);

            $cats = $cat->getChildren();

            $cats[] = $fcat;

            $where .= 'and cat_id in (' . implode(',', $cats) . ') ';
        }


        // ---------------------------------------------------------
        // ПОИСК ПО НАЗВАНИЮ И АРТИКУЛУ
        // ---------------------------------------------------------

        if (strlen($searchkey) > 0) {

            $search = Item::qstr('%' . $searchkey . '%');

            $where .= "and (
                itemname like " . $search . "
                or item_code like " . $search . "
            ) ";
        }


        // ---------------------------------------------------------
        // ТОВАРЫ
        // ---------------------------------------------------------

        $itemlist = Item::find($where, 'itemname asc');


        // ---------------------------------------------------------
        // СКЛАДЫ
        // ---------------------------------------------------------

        $storelist = Store::getList();

        if (\App\System::getUser()->showotherstores) {
            $storelist = Store::getListAll();
        }


        // ---------------------------------------------------------
        // ОСТАТКИ
        // ---------------------------------------------------------

        $siqty = array();

        $stlist = array();


        // ---------------------------------------------------------
        // КАСТОМНЫЕ ПОЛЯ
        // ---------------------------------------------------------

        $cflist = $common['cflist'] ?? [];

        if ($fcust == false) {
            $cflist = [];
        }

        $cfnames = [];

        foreach ($cflist as $c) {
            $cfnames[] = $c->name;
        }


        // ---------------------------------------------------------
        // ЗАГРУЗКА ОСТАТКОВ
        // ---------------------------------------------------------

        $conn = \ZDB\DB::getConnect();

        $rs = $conn->Execute("
            select
                store_id,
                item_id,
                coalesce(sum(qty), 0) as qty
            from store_stock_view
            where itemdisabled<>1
            group by store_id, item_id
        ");


        foreach ($rs as $row) {

            $qty = doubleval($row['qty']);

            $siqty[
                $row['store_id'] . '_' . $row['item_id']
            ] = $qty;
        }


        // ---------------------------------------------------------
        // ФОРМИРОВАНИЕ ДЕТАЛЕЙ
        // ---------------------------------------------------------

        $detail = array();


        foreach ($itemlist as $item) {

            $r = array();

            $r['itemname'] = $item->itemname;

            $r['item_code'] = $item->item_code;

            $r['brand'] = $item->manufacturer;

            $r['minqty'] =
                $item->minqty > 0
                ? H::fqty($item->minqty)
                : '';


            $flag = true;

            $r['stlistcol'] = array();


            // -----------------------------------------------------
            // ОСТАТКИ ПО СКЛАДАМ
            // -----------------------------------------------------

            foreach ($storelist as $store_id => $storename) {

                $qty =
                    $siqty[
                        $store_id . '_' . $item->item_id
                    ] ?? 0;


                if (strlen($qty) == 0) {
                    $qty = 0;
                }


                // -------------------------------------------------
                // В МИНУСЕ
                // -------------------------------------------------

                if ($fminus) {

                    if ($qty < 0) {
                        $flag = false;
                    }
                }


                // -------------------------------------------------
                // МЕНЬШЕ МИНИМАЛЬНОГО
                // -------------------------------------------------

                if ($fmin && $item->minqty > 0) {

                    if ($qty < $item->minqty) {
                        $flag = false;
                    }
                }


                // -------------------------------------------------
                // ОБЫЧНЫЙ РЕЖИМ
                // -------------------------------------------------

                if (!$fminus && !$fmin) {

                    if ($qty > 0) {
                        $flag = false;
                    }
                }


                $r['stlistcol'][] = array(
                    'qty' => H::fqty($qty)
                );
            }


            // -----------------------------------------------------
            // ЕСЛИ НЕ ВЫБРАНО "ВСІ"
            // -----------------------------------------------------

            if ($flag && !$fall) {
                continue;
            }


            // -----------------------------------------------------
            // КАСТОМНЫЕ ПОЛЯ
            // -----------------------------------------------------

            $r['cfcol'] = array();


            foreach ($cfnames as $fn) {

                foreach ($item->getcf() as $f) {

                    if ($fn === $f->name) {

                        $r['cfcol'][] = array(
                            'val' => $f->val
                        );
                    }
                }
            }


            $detail[] = $r;
        }


        // ---------------------------------------------------------
        // COLSPAN
        // ---------------------------------------------------------

        $colspan = 4;

        $colspan += count($storelist);

        $colspan += count($cfnames);


        // ---------------------------------------------------------
        // HEADER
        // ---------------------------------------------------------

        $header = array(
            "date" => H::fd(time()),

            "ver" => false,

            "colspan" => $colspan,

            "cfnames" =>
                \App\Util::tokv($cfnames),

            "_detail" =>
                $detail,

            "storescol" =>
                \App\Util::tokv($storelist)
        );


        // ---------------------------------------------------------
        // ОТЧЁТ
        // ---------------------------------------------------------

        $report =
            new \App\Report('report/storeitems.tpl');


        return $report->generate($header);
    }


    /**
     * Вертикальный режим
     */
    private function generateReportVer() {

        $common = \App\System::getOptions('common');


        $fmin =
            $this->filter->fmin->isChecked();

        $fminus =
            $this->filter->fminus->isChecked();

        $fcust =
            $this->filter->fcust->isChecked();


        $fcat =
            $this->filter->searchcat->getValue();


        // ---------------------------------------------------------
        // ПОИСК
        // ---------------------------------------------------------

        $searchkey =
            trim($this->filter->searchkey->getText());


        // ---------------------------------------------------------
        // ФИЛЬТР
        // ---------------------------------------------------------

        $where = 'disabled<>1 ';


        // ---------------------------------------------------------
        // КАТЕГОРИЯ
        // ---------------------------------------------------------

        if ($fcat > 0) {

            $cat =
                \App\Entity\Category::load($fcat);

            $cats =
                $cat->getChildren();

            $cats[] =
                $fcat;

            $where .=
                'and cat_id in (' .
                implode(',', $cats) .
                ') ';
        }


        // ---------------------------------------------------------
        // ПОИСК ПО НАЗВАНИЮ / АРТИКУЛУ
        // ---------------------------------------------------------

        if (strlen($searchkey) > 0) {

            $search =
                Item::qstr('%' . $searchkey . '%');

            $where .= "and (
                itemname like " . $search . "
                or item_code like " . $search . "
            ) ";
        }


        // ---------------------------------------------------------
        // ТОВАРЫ
        // ---------------------------------------------------------

        $itemlist =
            Item::find($where, 'itemname asc');


        // ---------------------------------------------------------
        // СКЛАДЫ
        // ---------------------------------------------------------

        $storelist =
            Store::getList();

        if (\App\System::getUser()->showotherstores) {

            $storelist =
                Store::getListAll();
        }


        // ---------------------------------------------------------
        // ОСТАТКИ
        // ---------------------------------------------------------

        $siqty = array();

        $stlist = array();


        // ---------------------------------------------------------
        // КАСТОМНЫЕ ПОЛЯ
        // ---------------------------------------------------------

        $cflist =
            $common['cflist'] ?? [];


        if ($fcust == false) {
            $cflist = [];
        }


        $cfnames = [];


        foreach ($cflist as $c) {
            $cfnames[] =
                $c->name;
        }


        // ---------------------------------------------------------
        // ЗАГРУЗКА ОСТАТКОВ
        // ---------------------------------------------------------

        $conn =
            \ZDB\DB::getConnect();


        $rs = $conn->Execute("
            select
                store_id,
                item_id,
                coalesce(sum(qty), 0) as qty
            from store_stock_view
            where itemdisabled<>1
            group by store_id, item_id
        ");


        foreach ($rs as $row) {

            $qty =
                doubleval($row['qty']);

            $siqty[
                $row['store_id'] .
                '_' .
                $row['item_id']
            ] = $qty;
        }


        // ---------------------------------------------------------
        // ФОРМИРОВАНИЕ ОТЧЁТА
        // ---------------------------------------------------------

        $detail = array();


        foreach ($storelist as $store_id => $storename) {

            $detailitems = array();


            foreach ($itemlist as $item) {

                $r = array();

                $r['itemname'] =
                    $item->itemname;

                $r['item_code'] =
                    $item->item_code;

                $r['brand'] =
                    $item->manufacturer;

                $r['minqty'] =
                    $item->minqty > 0
                    ? H::fqty($item->minqty)
                    : '';


                $flag = true;

                $r['qty'] = 0;


                // -------------------------------------------------
                // ОСТАТОК
                // -------------------------------------------------

                $qty =
                    $siqty[
                        $store_id .
                        '_' .
                        $item->item_id
                    ] ?? 0;


                if (strlen($qty) == 0) {
                    $qty = 0;
                }


                // -------------------------------------------------
                // МИНУС
                // -------------------------------------------------

                if ($fminus) {

                    if ($qty < 0) {
                        $flag = false;
                    }
                }


                // -------------------------------------------------
                // МИНИМУМ
                // -------------------------------------------------

                if ($fmin && $item->minqty > 0) {

                    if ($qty < $item->minqty) {
                        $flag = false;
                    }
                }


                // -------------------------------------------------
                // ОБЫЧНЫЙ РЕЖИМ
                // -------------------------------------------------

                if (!$fminus && !$fmin) {

                    if ($qty > 0) {
                        $flag = false;
                    }
                }


                $r['qty'] =
                    H::fqty($qty);


                // -------------------------------------------------
                // ФИЛЬТР НУЛЕВЫХ
                // -------------------------------------------------

                if ($flag) {
                    continue;
                }


                // -------------------------------------------------
                // КАСТОМНЫЕ ПОЛЯ
                // -------------------------------------------------

                $r['cfcol'] = array();


                foreach ($cfnames as $fn) {

                    foreach ($item->getcf() as $f) {

                        if ($fn === $f->name) {

                            $r['cfcol'][] =
                                array(
                                    'val' => $f->val
                                );
                        }
                    }
                }


                $detailitems[] =
                    $r;
            }


            if (count($detailitems) > 0) {

                $detail[] =
                    array(
                        'items' =>
                            $detailitems,

                        'storename' =>
                            $storename
                    );
            }
        }


        // ---------------------------------------------------------
        // COLSPAN
        // ---------------------------------------------------------

        $colspan = 5;

        $colspan +=
            count($cfnames);


        // ---------------------------------------------------------
        // HEADER
        // ---------------------------------------------------------

        $header = array(

            "date" =>
                H::fd(time()),

            "ver" =>
                true,

            "colspan" =>
                $colspan,

            "cfnames" =>
                \App\Util::tokv($cfnames),

            "_detail" =>
                $detail,

            "storescol" =>
                []
        );


        // ---------------------------------------------------------
        // ОТЧЁТ
        // ---------------------------------------------------------

        $report =
            new \App\Report('report/storeitems.tpl');


        return $report->generate($header);
    }


    /**
     * Получение данных отчёта
     */
    public function getData() {

        $html =
            $this->generateReport();


        \App\Session::getSession()->printform =
            "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>"
            . $html .
            "</body></html>";


        return $html;
    }
}

