<?php

namespace App\Pages\Doc;

use App\Application as App;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Store;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;

/**
 * Страница  ввода   оприходование с  производства
 */
class ProdReceipt extends \App\Pages\Base
{
    public $_itemlist  = array();
    private $_doc;
    private $_basedocid = 0;
    private $_rowid     = -1;

    /**
    * @param mixed $docid      редактирование
    * @param mixed $basedocid  создание на  основании
    * @param mixed $st_id      этап  производства
    */
    public function __construct($docid = 0, $basedocid = 0, $st_id = 0) {
        parent::__construct();


        $common = System::getOptions("common");

        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));
        $this->docform->add(new Date('document_date'))->setDate(time());
        $this->docform->add(new DropDownChoice('parea', \App\Entity\ProdArea::findArray("pa_name", ""), 4));
        $this->docform->add(new DropDownChoice('store', Store::getList(), H::getDefStore()));
		
        $this->docform->add(new TextArea('notes'));
        $this->docform->add(new DropDownChoice('emp', \App\Entity\Employee::findArray("emp_name", "disabled<>1", "emp_name"))) ;
		
        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');
		
        $this->docform->add(new SubmitButton('import'))->onClick($this, 'importdocOnClick');        
        $this->docform->add(new \Zippy\Html\Form\File('importfile'));
        $this->docform->add(new Label('total'));
        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new AutocompleteTextInput('edititem'))->onText($this, 'OnAutoItem');
        $this->editdetail->edititem->onChange($this, 'OnChangeItem', true);
		
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new TextInput('editsnumber'));
        $this->editdetail->add(new Date('editsdate'));
		$this->docform->add(new TextInput('barcode'));
		$this->docform->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');


        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('saverow'))->onClick($this, 'saverowOnClick');

        if ($docid > 0) {    //загружаем   содержимое  документа на страницу
            $this->_doc = Document::load($docid)->cast();
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->notes->setText($this->_doc->notes);
            $this->docform->document_date->setDate($this->_doc->document_date);
            $this->docform->parea->setValue($this->_doc->headerdata['parea']);
            $this->docform->emp->setValue($this->_doc->headerdata['emp']);
            $this->docform->store->setValue($this->_doc->headerdata['store']);

            $this->_itemlist = $this->_doc->unpackDetails('detaildata');
        } else {
            $this->_doc = Document::create('ProdReceipt');
            $this->docform->document_number->setText($this->_doc->nextNumber());
            if ($basedocid > 0) {  //создание на  основании
                $basedoc = Document::load($basedocid);
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                    if ($basedoc->meta_name == 'ProdReceipt') {
                        $this->docform->store->setValue($basedoc->headerdata['store']);

                        $this->docform->parea->setValue($basedoc->headerdata['parea']);

                        $this->_itemlist = $basedoc->unpackDetails('detaildata');
                    }
                }
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                    if ($basedoc->meta_name == 'Order') {
                        $this->docform->notes->setText('Замовлення ' . $basedoc->document_number);
                        foreach ($basedoc->unpackDetails('detaildata') as $item) {
                            $item->price = $item->getLastPartion();
                            $this->_itemlist[$item->item_id] = $item;
							}
                        }
				}
				if ($basedoc->meta_name == 'Task') {

					$this->docform->notes->setText('Наряд ' . $basedoc->document_number);
					$this->docform->parea->setValue($basedoc->headerdata['parea']);

					foreach ($basedoc->unpackDetails('prodlist') as $item) {
						$item->price = $item->getProdprice();
						$this->_itemlist[$item->item_id] = $item;
                    }
                }
            }

            if ($st_id > 0) {
                $st = \App\Entity\ProdStage::load($st_id);
                $this->docform->parea->setValue($st->pa_id);
                $this->_doc->headerdata['st_id'] = $st->st_id;
                $this->_doc->headerdata['pp_id'] = $st->pp_id;
                $this->docform->notes->setText($st->stagename);

                $this->docform->emp->setVisible(false);
                $st= \App\Entity\ProdStage::load($st_id);
                $i=1;
                foreach($st->itemlist as $it){
                    $item = Item::load($it->item_id) ;
                    $item->quantity = $it->quantity;
                    $this->_itemlist[$i++]=$item;
                }

            }


        }
        $this->calcTotal();
        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'))->Reload();
        if (false == \App\ACL::checkShowDoc($this->_doc)) {
            return;
        }
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('item', $item->itemname));
        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('msr', $item->msr));
        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));
        $row->add(new Label('snumber', $item->snumber));
        $row->add(new Label('sdate', $item->sdate > 0 ? \App\Helper::fd($item->sdate) : ''));

        $row->add(new Label('amount', H::fa(doubleval($item->price) * doubleval($item->quantity))));
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->edit->setVisible($item->old != true);

        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function editOnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);

        $this->editdetail->editquantity->setText($item->quantity);
        $this->editdetail->editprice->setText($item->price);
        $this->editdetail->editsnumber->setText($item->snumber);
        $this->editdetail->editsdate->setDate($item->sdate);
        $this->editdetail->edititem->setKey($item->item_id);
		
        $this->editdetail->edititem->setText($item->itemname);
        $this->_rowid = $item->item_id;
		
		


    }
public function addcodeOnClick($sender) {
    $codes_text = trim($this->docform->barcode->getText());
    if ($codes_text == '') {
        $this->setError("Введіть штрихкод(и)");
        return;
    }

    $codes = preg_split('/[^\w\*\x\x{0445}]+/u', $codes_text, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($codes as $raw) {
        $raw = trim($raw);
        if ($raw == '') continue;

        $qty = 1;
        $code = '';

        if (preg_match('/^(\d+)[\*\x\x{0445}](.+)$/u', $raw, $matches)) {
            $qty = intval($matches[1]);
            $code = $matches[2];
        } else {
            $code = $raw;
            $qty = 1;
        }

        $code = trim($code);

        $q = Item::qstr($code);
        $item = Item::getFirst("bar_code={$q} OR item_code={$q}");

        if ($item == null) {
            $this->setError("Товар з штрихкодом '{$code}' не знайдено");
            continue;
        }

        if ($item->item_type != 4) {
            $this->setError("Товар '{$item->itemname}' не є готовою продукцією");
            continue;
        }

        if ($this->_doc->headerdata['st_id'] > 0) {
            $st = \App\Entity\ProdStage::load($this->_doc->headerdata['st_id']);
            if (count($st->itemlist) > 0) {
                $ids = array_keys($st->itemlist);
                if (!in_array($item->item_id, $ids)) {
                    $this->setError("ТМЦ {$item->itemname} не в перелiку на етапi");
                    continue;
                }
            }
        }

        $item->price = $item->getProdprice();
        $item->snumber = '';
        $item->sdate = '';

        if (isset($this->_itemlist[$item->item_id])) {
            $this->_itemlist[$item->item_id]->quantity += $qty;
        } else {
            $item->quantity = $qty;
            $this->_itemlist[$item->item_id] = $item;
        }

        // Вывод уведомления через встроенный метод
        $this->setSuccess("Додано: {$item->itemname} Кількість: {$qty} шт");
    }

    $this->docform->barcode->setText('');
    $this->calcTotal();
    $this->docform->detail->Reload();
}







    public function deleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }
        $item = $sender->owner->getDataItem();
        // unset($this->_itemlist[$item->item_id]);
        $this->_itemlist = array_diff_key($this->_itemlist, array($item->item_id => $this->_itemlist[$item->item_id]));
        $this->calcTotal();
        $this->docform->detail->Reload();
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);
        $this->_rowid = 0;
    }

    public function saverowOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }


        $id = $this->editdetail->edititem->getKey();

        if ($id == 0) {
            $this->setError("Не обрано товар");
            return;
        }

        if($this->_doc->headerdata['st_id'] >0) {
            $st= \App\Entity\ProdStage::load($this->_doc->headerdata['st_id']);
            
            if( count($st->itemlist)>0) {
               $ids= array_keys($st->itemlist) ; 
               
               if(!in_array($item->item_id,$ids)) {
                    $this->setError( "ТМЦ не в перелiку  на  етапi");
                    return;
          
               }
               
            }
            
        }

        $item = Item::load($id);

        $item->quantity = $this->editdetail->editquantity->getDouble();
        $item->price = $this->editdetail->editprice->getDouble();
        if ($item->price == 0) {
            $this->setWarn("Не вказана ціна");
        }
        $item->snumber = $this->editdetail->editsnumber->getText();
        $item->sdate = $this->editdetail->editsdate->getDate();
        if ($item->sdate == false) {
            $item->sdate = '';
        }
        if (strlen($item->snumber) == 0 && $item->useserial == 1 && $this->_tvars["usesnumber"] == true) {
            $this->setError("Потрібна партія виробника");
            return;
        }


        $tarr = array();

        foreach ($this->_itemlist as $k => $value) {

            if ($this->_rowid > 0 && $this->_rowid == $k) {
                $tarr[$item->item_id] = $item;    // заменяем
        } else {
                $tarr[$k] = $value;    // старый
            }
        }

        if ($this->_rowid == 0) {        // в конец
            $tarr[$item->item_id] = $item;
        }
        $this->_itemlist = $tarr;
        $this->_rowid = 0;

        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();
        $this->calcTotal();
        //очищаем  форму
        $this->editdetail->edititem->setKey(0);
        $this->editdetail->edititem->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editsnumber->setText("");
        $this->editdetail->editsdate->setText("");
    }

    public function cancelrowOnClick($sender) {
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();
        $this->calcTotal();
    }

    public function savedocOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }
        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $this->_doc->notes = $this->docform->notes->getText();
        if ($this->checkForm() == false) {
            return;
        }

        $this->calcTotal();

        $this->_doc->headerdata['parea'] = $this->docform->parea->getValue();
        $this->_doc->headerdata['pareaname'] = $this->docform->parea->getValueName();
        $this->_doc->headerdata['store'] = $this->docform->store->getValue();
        $this->_doc->headerdata['storename'] = $this->docform->store->getValueName();
        $this->_doc->headerdata['emp'] = $this->docform->emp->getValue();
        $this->_doc->headerdata['empname'] = $this->docform->emp->getValueName();

        $this->_doc->packDetails('detaildata', $this->_itemlist);

        $this->_doc->amount = $this->docform->total->getText();
        $isEdited = $this->_doc->document_id > 0;
        $this->_doc->payamount = 0;


        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            if ($this->_basedocid > 0) {
                $this->_doc->parent_id = $this->_basedocid;
                $this->_basedocid = 0;
            }
            $this->_doc->save();

            if ($sender->id == 'execdoc') {
                if (!$isEdited) {
                    $this->_doc->updateStatus(Document::STATE_NEW);
                }

                $this->_doc->updateStatus(Document::STATE_EXECUTED);
            } else {
                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);
            }


            $conn->CommitTrans();
        } catch(\Throwable $ee) {
            global $logger;
            $conn->RollbackTrans();
            if ($isEdited == false) {
                $this->_doc->document_id = 0;
            }
            $this->setError($ee->getMessage());

            $logger->error( $ee->getMessage()  );
            $logger->error( $ee->getTraceAsString()  );
           return;
        }
        App::Redirect("\\App\\Pages\\Register\\StockList");

    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal() {

        $total = 0;

        foreach ($this->_itemlist as $item) {
            $item->amount = doubleval($item->price) * doubleval($item->quantity);
            $total = $total + $item->amount;
        }
        $this->docform->total->setText(H::fa($total));
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm() {
        if (strlen($this->_doc->document_number) == 0) {
            $this->setError('Введіть номер документа');
        }
        if (false == $this->_doc->checkUniqueNumber()) {
            $next = $this->_doc->nextNumber();
            $this->docform->document_number->setText($next);
            $this->_doc->document_number = $next;
            if (strlen($next) == 0) {
                $this->setError('Не створено унікальный номер документа');
            }
        }
        if (count($this->_itemlist) == 0) {
            $this->setError("Не введено товар");
        }
        if (($this->docform->store->getValue() > 0) == false) {
            $this->setError("Не обрано склад");
        }


        return !$this->isError();
    }

    public function beforeRender() {
        parent::beforeRender();

        $this->calcTotal();
    }

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function OnAutoItem($sender) {
   
        $text = trim($sender->getText());
        $like  = Item::qstr('%'.$text.'%');
        
        return Item::findArray("itemname","  disabled <> 1 and  item_type  in (4,5)     and  (itemname like {$like} or item_code like {$like}   or   bar_code like {$like} )");
    }   
    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $item = \App\Entity\Item::load($id);

        $price = $item->getProdprice();
        $this->editdetail->editprice->setText($price > 0 ? H::fa($price) : '');

    }

    //импорт  с  ексель
    public function importdocOnClick($sender) {
        $file = $this->docform->importfile->getFile();
        
        if (strlen($file['tmp_name']) == 0) {

            $this->setError('Не выбран файл');
            return;
        }

        $data = array();
        $oSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']); 


        $oCells = $oSpreadsheet->getActiveSheet()->getCellCollection();

        for ($iRow =2; $iRow <= $oCells->getHighestRow(); $iRow++) {
            $row = array();
            for ($iCol = 'A'; $iCol <= $oCells->getHighestColumn(); $iCol++) {
                $oCell = $oCells->get($iCol . $iRow);
                if ($oCell) {
                    $row[$iCol] = $oCell->getValue();
                }
            }
            $data[$iRow] = $row;
        }
        unset($oSpreadsheet);
       
        foreach ($data as $row) {
          // if(strlen($row['A'])==0) continue;
           if(strlen($row['B'])==0) continue;
           $code = Item::qstr($row['B']);
           $item = Item::getFirst(" item_code = {$code}");
           if($item==null) {
               
               $item = new  Item() ;
               $item->itemname = $row['A'] ;
               $item->item_code = $row['B'] ;
             //  $item->save() ;
               H::log("Не найден  артикул ".$code)  ;
               continue;   
           }
           

           $item->quantity = $row['D'];
          
           $item->price = $item->getProdprice();
 
           $this->_itemlist[$item->item_id] = $item;
            
        }
       
        $this->docform->detail->Reload();
        
    }
    
    
}
