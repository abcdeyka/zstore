<?php



namespace App\Pages\Register;

use App\Application as App;
use App\Entity\Doc\Document;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\DataList\Paginator;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;
use Zippy\Html\Panel;
use App\Entity\Pay;

/**
 * журнал  заказов
 */
class OrderList extends \App\Pages\Base
{
    private $_doc = null;
    private $_issms = false; //подключен  смс  сервис
    public $_itemlist =[];

    /**
     *

     * @return DocList
     */
    public function __construct() {
        parent::__construct();
        if (false == \App\ACL::checkShowReg('OrderList')) {
            \App\Application::RedirectHome() ;
        }
        $this->_issms = (System::getOption('sms', 'smstype')??0) >0 ;

        $this->add(new Panel("listpanel"));

        $this->listpanel->add(new Form('filter'))->onSubmit($this, 'filterOnSubmit');

        $this->listpanel->filter->add(new TextInput('searchnumber'));
        $this->listpanel->filter->add(new TextInput('searchtext'));
        $this->listpanel->filter->add(new DropDownChoice('status', array(0 => 'Вiдкритi', 1 => 'Новi',2 => 'До сплати', 3 => 'Всi'), 0));
        $this->listpanel->filter->add(new DropDownChoice('salesource', H::getSaleSources(), 0));

        $doclist = $this->listpanel->add(new DataView('doclist', new OrderDataSource($this), $this, 'doclistOnRow'));

        $this->listpanel->add(new Paginator('pag', $doclist));
        $doclist->setPageSize(H::getPG());
        $this->listpanel->add(new ClickLink('csv', $this, 'oncsv'));


        $this->add(new Panel("statuspan"))->setVisible(false);

        $this->statuspan->add(new Form('statusform'));

        $this->statuspan->statusform->add(new SubmitButton('bclose'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('binp'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('brd'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('brec'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bsent'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bscan'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bsplit'))->onClick($this, 'statusOnSubmit');


        $this->statuspan->statusform->add(new SubmitButton('bpos'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bgi'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bginv'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bco'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bref'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bcopy'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bttn'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('btask'))->onClick($this, 'statusOnSubmit');
        $this->statuspan->statusform->add(new SubmitButton('bprint'))->onClick($this, 'printlabels', true);

        $this->statuspan->statusform->add(new \Zippy\Html\Link\RedirectLink('btopay'));

        $this->statuspan->add(new \App\Widgets\DocView('docview'));

        $this->statuspan->add(new Form('moveform'));
        $this->statuspan->moveform->add(new DropDownChoice('brmove', \App\Entity\Branch::getList(), \App\ACL::getCurrentBranch()))->onChange($this, "onBranch", true);
        $this->statuspan->moveform->add(new DropDownChoice('usmove', array(), 0));
        $this->statuspan->moveform->add(new SubmitButton('bmove'))->onClick($this, 'MoveOnSubmit');

        $this->statuspan->add(new Form('resform'))->setVisible(false);

        $this->statuspan->resform->add(new SubmitButton('bres'))->onClick($this, 'resOnSubmit');
        $this->statuspan->resform->add(new SubmitButton('bunres'))->onClick($this, 'resOnSubmit');
        $this->statuspan->resform->add(new DropDownChoice('store', \App\Entity\Store::getList(), H::getDefStore()));


        $this->listpanel->doclist->Reload();

  
        $this->add(new Panel("editpanel"))->setVisible(false);
        $this->editpanel->add(new Label("editdn"));
        $this->editpanel->add(new Label("editchat"));
        $this->editpanel->add(new Form("editform"));
        $this->editpanel->editform->add(new SubmitButton('editcancel'))->onClick($this, 'editOnSubmit');
        $this->editpanel->editform->add(new SubmitButton('editsave'))->onClick($this, 'editOnSubmit');
        $this->editpanel->editform->add(new SubmitButton('editready'))->onClick($this, 'editOnSubmit');
        $this->editpanel->editform->add(new DataView('edititemlist', new \Zippy\Html\DataList\ArrayDataSource($this, '_itemlist'), $this, 'editlistOnRow'));
        $this->editpanel->editform->add(new TextInput('editbarcode'));
        $this->editpanel->editform->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');

        $this->add(new Panel("splitpanel"))->setVisible(false);
        $this->splitpanel->add(new Label("splitdn"));
        $this->splitpanel->add(new Form("splitform"));
        $this->splitpanel->splitform->add(new SubmitButton('splitcancel'))->onClick($this, 'splitOnSubmit');
        $this->splitpanel->splitform->add(new SubmitButton('splitsave'))->onClick($this, 'splitOnSubmit');
        $this->splitpanel->splitform->add(new DataView('splititemlist', new \Zippy\Html\DataList\ArrayDataSource($this, '_itemlist'), $this, 'splitlistOnRow'));
 
        
    }

    public function filterOnSubmit($sender) {

        $this->statuspan->setVisible(false);
      
        $this->listpanel->doclist->Reload();

    }

    public function doclistOnRow(\Zippy\Html\DataList\DataRow $row) {
        $doc = $row->getDataItem()->cast();

        $n = $doc->document_number;
        if(strlen($doc->headerdata['ocorder'] ?? '')>0) {
            $n = $n . " (OC '{$doc->headerdata['ocorder']}')"  ;
        }
        if(strlen($doc->headerdata['wcorder'] ?? '')>0) {
            $n = $n . " (WC '{$doc->headerdata['wcorder']}')"  ;
        }
        if(strlen($doc->headerdata['puorder'] ?? '')>0) {
            $n = $n . " (PU '{$doc->headerdata['puorder']}')"  ;
        }


        $row->add(new ClickLink('number', $this, 'showOnClick'))->setValue($n);


        $row->add(new Label('date', H::fd($doc->document_date)));
        $row->add(new Label('onotes', $doc->notes));
        $row->add(new Label('emp', $doc->username));


        $row->add(new  \Zippy\Html\Link\BookmarkableLink('customer'))->setValue($doc->customer_name);
        $row->customer->setAttribute('onclick', "customerInfo({$doc->customer_id});") ;
        $row->add(new Label('amount', H::fa($doc->getAmountReg() )));


        $row->add(new Label('ispay'))->setVisible($doc->getHD('paytype') != 3);
        
        if($doc->getHD('waitpay')==1){
            $row->ispay->setAttribute('class','fa fa-credit-card text-warning');
            $row->ispay->setAttribute('title','До сплати');            
        }   else {
            $row->ispay->setAttribute('class','fa fa-credit-card text-success');            
            $row->ispay->setAttribute('title','Оплачено');            
        }
        $row->add(new Label('isreserved'))->setVisible($this->docHasReserve($doc));

        $stname = Document::getStateName($doc->state);

        $row->add(new Label('state', $stname));
        $row->state->setText('<span class="badge  text-bg-secondary">' . $stname . '</span>', true);
        if ($doc->state == Document::STATE_NEW) {
            $row->state->setText('<span class="badge  text-bg-info">' . $stname . '</span>', true);
        }
        if ($doc->state == Document::STATE_READYTOSHIP || $doc->state == Document::STATE_INSHIPMENT || $doc->state == Document::STATE_DELIVERED) {
            $row->state->setText('<span class="badge  text-bg-success">' . $stname . '</span>', true);
        }
        if ($doc->state == Document::STATE_INPROCESS) {
            $row->state->setText('<span class="badge  text-bg-primary">' . $stname . '</span>', true);
        }

        if ($doc->state == Document::STATE_CLOSED || $doc->state == Document::STATE_EXECUTED) {
            $row->state->setText('<span class="badge  text-bg-secondary">' . $stname . '</span>', true);
        }
        if ($doc->state == Document::STATE_FAIL) {
            $row->state->setText('<span class="badge  text-bg-danger">' . $stname . '</span>', true);
        }
     
        $row->add(new ClickLink('show'))->onClick($this, 'showOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        if ($doc->state < Document::STATE_EXECUTED || $doc->state == Document::STATE_INPROCESS) {
            $row->edit->setVisible(true);
       // } else {
       //     $row->edit->setVisible(false);
        }
        $row->setAttribute('data-did', $doc->document_id);
     

        $ch = $this->checkChat($doc);
        if($ch) {
            $row->add(new Label('cchat'))->setVisible(true);
            $row->cchat->setAttribute('onclick', "opencchat({$doc->document_id})");
            $row->cchat->setAttribute('class', "fa fa-comments");

            $m = \App\Entity\Message::getFirst("item_id={$doc->document_id} and item_type=" .\App\Entity\Message::TYPE_CUSTCHAT, "message_id desc");
            if($m != null) {
                if($m->user_id >0) { //отправлен  вопрос
                    $row->cchat->setAttribute('class', "fa fa-comments text-warn");
                }
                if($m->user_id ==0) { //получен  ответ
                    $row->cchat->setAttribute('class', "fa fa-comments text-success");
                }
            }
        }
    }

    private function checkChat($doc) {
        $ret = false ;
        if($this->_issms && ($doc->state < Document::STATE_EXECUTED || $doc->state == Document::STATE_INPROCESS)) {

            $phone= $doc->headerdata['phone'] ??'';
            if($phone=='') {
                $c =  \App\Entity\Customer::load($doc->customer_id) ;
                $phone = $c->phone;
            }

            if(strlen($phone)>0) {
                $ret = true;
            }
        }
        return $ret;
    }

    /** Резерв саме TAG_RESERV. hasStore() ловить будь-яке проведення (наприклад TAG_SELL). */
    private function docHasReserve($doc) {
        if ($doc == null) {
            return false;
        }
        $conn = \ZDB\DB::getConnect();
        $tag = \App\Entity\Entry::TAG_RESERV;
        $id  = intval($doc->document_id);
        return intval($conn->GetOne("select coalesce(count(*),0) from entrylist where tag={$tag} and document_id={$id}")) > 0;
    }

    /** Стирає всі складські проводки замовлення (резерв, виробництво, старі дублі). */
    private function clearOrderEntries($docId) {
        $conn = \ZDB\DB::getConnect();
        $docId = intval($docId);
        if ($docId <= 0) {
            return;
        }
        $conn->Execute("delete from entrylist where document_id={$docId}");
    }

    private function safeUnreserve() {
        if ($this->_doc == null) {
            return;
        }
        $this->_doc = Document::load($this->_doc->document_id);
        if ($this->_doc == null) {
            return;
        }
        $this->_doc = $this->_doc->cast();
        if (method_exists($this->_doc, 'unreserve')) {
            $this->_doc->unreserve();
        }
        $this->clearOrderEntries(intval($this->_doc->document_id));
    }

    public function resOnSubmit($sender) {
        if ($this->_doc == null) {
            return;
        }
        $this->_doc = Document::load($this->_doc->document_id);
        $this->_doc = $this->_doc->cast();

        $conn = \ZDB\DB::getConnect();
        $docId = intval($this->_doc->document_id);

        if ($sender->id == "bres") {
            $store = $this->statuspan->resform->store->getValue();
            if($store == 0) {
                return;
            }

            $conn->BeginTrans();

            try {
                $this->clearOrderEntries($docId);

                $this->_doc->headerdata['store'] = $store;
                $this->_doc->headerdata['storename'] = $this->statuspan->resform->store->getValueName();
                $this->_doc->save() ;
                $this->_doc->reserve();

                $conn->CommitTrans();

            } catch(\Exception $e) {
                $this->setError($e->getMessage()) ;
                $conn->RollbackTrans();
                return;
            }

            $this->statuspan->resform->bres->setVisible(false);
            $this->statuspan->resform->store->setVisible(false);
            $this->statuspan->resform->bunres->setVisible(true);

        }
        if ($sender->id == "bunres") {
            $conn->BeginTrans();
            try {
                if (method_exists($this->_doc, 'unreserve')) {
                    $this->_doc->unreserve();
                }
                $this->clearOrderEntries($docId);
                $this->_doc->headerdata['store'] = 0;
                $this->_doc->headerdata['storename'] = '';
                $this->_doc->save();
                $conn->CommitTrans();
            } catch(\Exception $e) {
                $this->setError($e->getMessage());
                $conn->RollbackTrans();
                return;
            }
            $this->statuspan->resform->bunres->setVisible(false);
            $this->statuspan->resform->bres->setVisible(true);
            $this->statuspan->resform->store->setVisible(true);
        }
        $this->_doc = Document::load($this->_doc->document_id);
        $this->_doc = $this->_doc->cast();
        $this->listpanel->doclist->Reload(false);
        $this->statuspan->setVisible(true);
        $this->statuspan->statusform->setVisible(true);
        $this->statuspan->docview->setDoc($this->_doc);
        $this->updateStatusButtons();
        $this->goAnkor('dankor');
        $this->addJavaScript(" $(\"[data-did={$this->_doc->document_id}]\").addClass( 'table-active') ", true);
    }


    public function statusOnSubmit($sender) {
        if (\App\ACL::checkChangeStateDoc($this->_doc, true, true) == false) {
            return;
        }

        $state = $this->_doc->state;

      //проверяем  что есть ТТН
        $list = $this->_doc->getChildren('TTN');
        $ttn = count($list) > 0;
        $list = $this->_doc->getChildren('GoodsIssue');
        $gi = count($list) > 0;
        //  $list = $this->_doc->getChildren('Invoice');
        //   $invoice = count($list) > 0;
        $list = $this->_doc->getChildren('POSCheck');
        $pos = count($list) > 0;

    
    
        if ($sender->id == "btask") {
            $task = count($this->_doc->getChildren('Task')) > 0;

            if ($task) {

                $this->setWarn('Вже існує документ Наряд');
            }
            App::Redirect("\\App\\Pages\\Doc\\Task", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "bttn") {
            if ($ttn) {
                $this->setWarn('У замовлення вже є відправки');
            }
            $this->safeUnreserve();
            App::Redirect("\\App\\Pages\\Doc\\TTN", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "bcopy") {

            App::Redirect("\\App\\Pages\\Doc\\Order", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "bpos") {
            if ($pos) {
                $this->setWarn('Вже існує документ Чек');
            }
            $this->safeUnreserve();
            App::Redirect("\\App\\Pages\\Service\\ARMPos", 0, $this->_doc->document_id);
            return;
        }

        if ($sender->id == "bgi") {

            $this->safeUnreserve();
            App::Redirect("\\App\\Pages\\Doc\\GoodsIssue", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "bginv") {

            App::Redirect("\\App\\Pages\\Doc\\Invoice", 0, $this->_doc->document_id);
            return;
        }
        if ($sender->id == "bco") {

            App::Redirect("\\App\\Pages\\Doc\\OrderCust", 0, $this->_doc->document_id);
            return;
        }

        if ($sender->id == "bscan") {
            $this->openedit();
            return;
        }
        if ($sender->id == "bsplit") {
            $this->openSplit();
            return;
        }
      
        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();

        try {
            if($this->_doc->user_id==0)  {
               $this->_doc->user_id = \App\System::getUser()->user_id; 
               $this->_doc->save();
            } 
 
            if ($sender->id == "binp") {
                $this->_doc->updateStatus(Document::STATE_INPROCESS);
            }
            if ($sender->id == "brd") {
                $this->_doc->updateStatus(Document::STATE_READYTOSHIP);
            }
            if ($sender->id == "brec") {
                $this->_doc->updateStatus(Document::STATE_DELIVERED);
            }
            if ($sender->id == "bsent") {
                $this->_doc->updateStatus(Document::STATE_INSHIPMENT);
            }

            if ($sender->id == "bref") {
                $this->_doc->setHD('waitpay',0) ;
                $this->_doc->updateStatus(Document::STATE_FAIL);
                $this->_doc->setHD('waitpay',0); 
                $this->_doc->save();     

                $this->setWarn('Замовлення анульовано');
            }

            if ($sender->id == "bclose") {

  

                if($this->_doc->payamount >0 && $this->_doc->payamount>$this->_doc->payed && $gi == false) {
                    $this->setWarn('"Замовлення закрито без оплати"');
                }
                if($ttn== false && $gi == false && $this->_doc->getHD('dostore',0) ==0) {
                    $this->setWarn('Замовлення закрито без доставки');
                }

                $this->_doc->updateStatus(Document::STATE_CLOSED);
                $this->_doc->setHD('waitpay',0); 
                $this->_doc->save();     

            }
            $conn->CommitTrans();

        } catch(\Exception $e) {
            $this->setError($e->getMessage()) ;
            $conn->RollbackTrans();
            return;
        }
        
        
        
        $this->statuspan->setVisible(false);    
        $this->_doc = null;            
        $this->listpanel->doclist->Reload(false);
//        $this->updateStatusButtons();
    }

    public function updateStatusButtons() {
        $common = System::getOptions("common");

        $this->statuspan->statusform->bclose->setVisible(true);

        $state = $this->_doc->state;

        //доставлен
        $closed = $this->_doc->checkStates(array(Document::STATE_CLOSED)) > 0;
        //выполняется
        $inproc = $this->_doc->checkStates(array(Document::STATE_INPROCESS)) > 0;
        //аннулирован
        $ref = $this->_doc->checkStates(array(Document::STATE_REFUSED)) > 0;

        $this->statuspan->statusform->btopay->setVisible(false);
        $this->statuspan->statusform->brd->setVisible(false);
        $this->statuspan->statusform->brec->setVisible(false);
        $this->statuspan->statusform->bsent->setVisible(false);
        $this->statuspan->statusform->bscan->setVisible(false);
        $this->statuspan->statusform->bsplit->setVisible(false);
        $this->statuspan->moveform->setVisible(false);

        $this->statuspan->resform->setVisible(false);


     //   $this->statuspan->statusform->bscan->setAttribute('onclick', "openscan({$this->_doc->document_id})");


        //новый
        if ($state < Document::STATE_EXECUTED) {
            $this->statuspan->statusform->btask->setVisible(false);

            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->bcopy->setVisible(false);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
            $this->statuspan->statusform->bco->setVisible(false);
            $this->statuspan->statusform->binp->setVisible(true);
            $this->statuspan->statusform->brd->setVisible(false);
            $this->statuspan->statusform->brec->setVisible(false);
            $this->statuspan->statusform->bsent->setVisible(false);
            $this->statuspan->statusform->bscan->setVisible(false);
            $this->statuspan->statusform->bsplit->setVisible(false);
        } else {

            $this->statuspan->statusform->bclose->setVisible(true);
            $this->statuspan->statusform->bref->setVisible(true);
            $this->statuspan->statusform->binp->setVisible(false);
            $this->statuspan->statusform->bco->setVisible(true);
            $this->statuspan->statusform->btask->setVisible(true);
        }


        if ($ref) {
            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
            $this->statuspan->statusform->bscan->setVisible(false);
        }

        if ($state == Document::STATE_INPROCESS) {
            $this->statuspan->statusform->bsent->setVisible(true);
            $this->statuspan->statusform->brd->setVisible(true);
            $this->statuspan->statusform->bscan->setVisible(true);
            $this->statuspan->statusform->bsplit->setVisible(true);

            $this->statuspan->statusform->bttn->setVisible(true);
            $this->statuspan->statusform->bpos->setVisible(true);
            $this->statuspan->statusform->bgi->setVisible(true);
            $this->statuspan->statusform->bginv->setVisible(true);
        }
        if ($state == Document::STATE_READYTOSHIP) {
            $this->statuspan->statusform->bsent->setVisible(true);
            $this->statuspan->statusform->brec->setVisible(true);
           
            $this->statuspan->statusform->bttn->setVisible(true);
            $this->statuspan->statusform->bpos->setVisible(true);
            $this->statuspan->statusform->bgi->setVisible(true);
            $this->statuspan->statusform->bginv->setVisible(true);      
        }
      
        if ($state == Document::STATE_INSHIPMENT) {

            $this->statuspan->statusform->brec->setVisible(true);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
            $this->statuspan->statusform->btask->setVisible(false);
        }
        if ($state == Document::STATE_READYTOSHIP) {

            $this->statuspan->statusform->bttn->setVisible(true);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(true);
            $this->statuspan->statusform->bginv->setVisible(true);
            $this->statuspan->statusform->btask->setVisible(false);
        }
        if ($state == Document::STATE_DELIVERED) {

            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
            $this->statuspan->statusform->btask->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
        }
        //закрыт
        if ($state == Document::STATE_CLOSED) {

            $this->statuspan->statusform->bclose->setVisible(false);
            $this->statuspan->statusform->btask->setVisible(false);
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
            $this->statuspan->statusform->binp->setVisible(false);
            $this->statuspan->statusform->bref->setVisible(false);
            $this->statuspan->statusform->bttn->setVisible(false);
            $this->statuspan->statusform->brd->setVisible(false);
            $this->statuspan->statusform->bsent->setVisible(false);
            $this->statuspan->statusform->bscan->setVisible(false);
        }

        if ($state == Document::STATE_WP) {

            if($this->_doc->getHD('waitpay')==1) {
                $this->statuspan->statusform->btopay->setVisible(true);
                $this->statuspan->statusform->btopay->setLink("App\\PAges\\Register\\PayBayList", array($this->_doc->document_id));
            }

        }

        if ($state == Document::STATE_INPROCESS || $state == Document::STATE_FINISHED || $state == Document::STATE_READYTOSHIP) {
            $this->statuspan->resform->setVisible(true);
            $reserved = $this->docHasReserve($this->_doc);
            $this->statuspan->resform->bres->setVisible(!$reserved);
            $this->statuspan->resform->store->setVisible(!$reserved);
            $this->statuspan->resform->bunres->setVisible($reserved);
        }

        if ($this->_doc->payamount > 0 && $this->_doc->payamount > $this->_doc->payed) {
            // $this->statuspan->statusform->bclose->setVisible(false);
        }
        if ($state < 5) {
            $this->statuspan->statusform->bref->setVisible(true);
        }
        if ($state == Document::STATE_WAIT ) {
            $this->statuspan->statusform->binp->setVisible(true);
        }

        if($this->_doc->hasPayments() == false && ($state<4 || $state==Document::STATE_INPROCESS)) {
            $this->statuspan->moveform->setVisible(true);
        }


        $this->_tvars['askclose'] = false;
        if ($inproc == false || $closed == false) {
            $this->_tvars['askclose'] = true;
        }


        //проверяем  что уже есть отправка
        $list = $this->_doc->getChildren('TTN');

        if(count($list)>0) {
            $this->statuspan->resform->setVisible(false);
        }

       
        $list = $this->_doc->getChildren('GoodsIssue');

        if(count($list)>0) {
            $this->statuspan->resform->setVisible(false);
        }
    
    
        if ($this->_doc->hasPayments()) {
            $this->statuspan->statusform->bpos->setVisible(false);
        }
        
        $pt= $this->_doc->getHD('paytype');
        
        if ($pt ==1 ||  $pt==2) {
            $this->statuspan->statusform->bpos->setVisible(false);
            $this->statuspan->statusform->bgi->setVisible(false);
            $this->statuspan->statusform->bginv->setVisible(false);
        }

        if ($pt ==3  ) {
            $this->statuspan->statusform->bttn->setVisible(false);
        }

        if ( $this->_doc->getHD('dostore',0) ==1) {
            $this->statuspan->statusform->bttn->setVisible(false);
        }        
        
    }

    //просмотр
    public function showOnClick($sender) {
     
        $this->_doc = $sender->owner->getDataItem();
        if (false == \App\ACL::checkShowDoc($this->_doc, true)) {
            return;
        }
        $options = System::getOptions('common');
        
        
        $this->_doc = Document::load($this->_doc->document_id); 
          
        $this->_doc = $this->_doc->cast();

        $this->statuspan->setVisible(true);
        $this->statuspan->statusform->setVisible(true);
        $this->statuspan->statusform->setVisible(true);
        $this->statuspan->docview->setDoc($this->_doc);

       // $this->listpanel->doclist->Reload(false);
        $this->updateStatusButtons();
        $this->goAnkor('dankor');
        $this->_tvars['askclose'] = false;
        $conn= \ZDB\DB::getConnect() ;

        $stl = array() ;
        foreach($conn->Execute("select store_id,storename from stores") as $row) {
            $stl[$row['store_id']]=$row['storename'];
        }
        $this->_tvars['isciprod']=false;
        $this->_tvars['sitems'] = [];
        $this->_tvars['citems'] = [];
        foreach($this->_doc->unpackDetails('detaildata') as $it) {
            $ait=array('itemname'=>$it->itemname,'itemcode'=>$it->item_code,'itemqty'=>$it->quantity);

            //на  складе
            $ait['citemsstore']  =  array();
            $onstores=0;
            foreach($it->getQuantityAllStores( ) as $s=>$q) {
                
                if(0 < doubleval($q)) {
                    $ait['citemsstore'][] = array('itstore'=>$stl[$s],'itqty'=>H::fqty($q));
                    $onstores += $q;
                }
            }
            //у  поставщика
            $ait['citemscust']  =  array();
            foreach(\App\Entity\CustItem::find("item_id={$it->item_id} ") as $ci) {
                $cer = array('itcust'=>$ci->customer_name,'itcustcode'=>$ci->cust_code);
                $cer['itcustprice']  = H::fa($ci->price);
                $cer['itcustupdated']  = H::fd($ci->updatedon);

                $cer['itcustqty']  = doubleval($ci->quantity)> 0 ? H::fqty($ci->quantity) : "";


                $ait['citemscust'][]=$cer;
            }
         
           //готово  к производству
            $ait['ciprod']  =  array();
           

            if($options['useprod']==1) {
                $prod=[];
                $itpr=\App\Entity\Item::getFirst("disabled<> 1 and  item_id = {$it->item_id} and  item_id in(select pitem_id from item_set)") ;
                if($itpr instanceof \App\Entity\Item)  {
                    $max = 1000000;
                    $parts = \App\Entity\ItemSet::find("pitem_id=".$itpr->item_id) ;

                    foreach($parts as $part) {
                        $pi = \App\Entity\Item::load($part->item_id);
                        if($pi==null) {
                            continue;
                        }
                        $pqty = $pi->getQuantity();
                        if($pqty==0) {
                            $max=0;
                            break;
                        }
                        $t = $pqty/$part->qty;
                        if($t<$max) {
                            $max = $t;
                        }

                    }
                    if($max>0 && $max < 1000000) {
                        $ait['prqty']= H::fqty($max);
                        $this->_tvars['isciprod']=true;  //если хоть один  готов
                    }


                }         
               
            }
            
            $need=$it->quantity - $onstores;
            if($need >0) {
               $ait['toco']  =  "addItemToCO({$it->item_id},{$need})";
            } else {
               $ait['toco']  =  "addItemToCO({$it->item_id})";                
            }


            $this->_tvars['citems'][]=$ait;
       
           //в закупке
              
            $sitems=[];
            
            $corders= Document::find("meta_name='OrderCust' and state in(5,7) ")  ;
            
            foreach($corders as $o) {
               
                  foreach($o->unpackDetails('detaildata') as $cit) {
                       if($it->item_id==$cit->item_id) {
                           $r=[] ;
                           $r['dnum']  = $o->document_number;
                           $r['dd']  = ($o->headerdata['delivery_date'] ?? 0) >0 ? H::fd($o->headerdata['delivery_date']) :'';
                           $r['dc']  = $o->customer_name;
                           $sitems[$o->document_id] = $r;
                           break;
                       }
                  }
                
           }
       
           foreach($sitems as $_si ) {
              $this->_tvars['sitems'][] = $_si ;
           }

        }

        $this->_tvars['issitems']= count($sitems) >0;

        $this->statuspan->moveform->brmove->setValue($this->_doc->branch_id) ;
        $this->onBranch($this->statuspan->moveform->brmove);
        $this->statuspan->moveform->usmove->setValue($this->_doc->user_id);
        
        $this->addJavaScript(" $(\"[data-did={$this->_doc->document_id}]\").addClass( 'table-active') ",true)  ;
 
    }

    public function editOnClick($sender) {
        $doc = $sender->getOwner()->getDataItem()->cast();
        if (false == \App\ACL::checkEditDoc($doc, true)) {
            return;
        }
        $cc = $doc->canCanceled();
        if (strlen($cc) > 0) {
            $this->setError($cc);

            return;
        }
        if($this->docHasReserve($doc)) {
           $doc->setHD('doreserv',1);
        }
        $doc->updateStatus(Document::STATE_CANCELED);
        $doc->payed = 0;
        $doc->save();
        App::Redirect("\\App\\Pages\\Doc\\Order", $doc->document_id);
    }

    public function oncsv($sender) {
        $list = $this->listpanel->doclist->getDataSource()->getItems(-1, -1, 'document_id');

        $header = array();
        $data = array();

        $i = 0;
        foreach ($list as $d) {
            $i++;
            $data['A' . $i] = H::fd($d->document_date);
            $data['B' . $i] = $d->document_number;
            $data['C' . $i] = $d->customer_name;
            $data['D' . $i] = $d->amount;
            $data['E' . $i] = Document::getStateName($d->state);
            $data['F' . $i] = $d->notes;
        }

        H::exportExcel($data, $header, 'orderlist.xlsx');
    }

    public function onBranch($sender) {
        $id = $sender->getValue();
        $users = array(0=> "Не обрано" );

        foreach(\App\Entity\User::getByBranch($id) as $id=>$u) {
            $users[$id] = $u ;
        }

        $this->statuspan->moveform->usmove->setOptionList($users);
    }

    public function moveOnSubmit($sender) {
        $br = intval($this->statuspan->moveform->brmove->getValue());
        $us = $this->statuspan->moveform->usmove->getValue();
        if($br>0) {
            $this->_doc->branch_id = $br;
        }
        if($us>0) {
            $this->_doc->user_id = $us;
        }

        if($br>0 || $us>0) {
            $this->_doc->save();
            $this->listpanel->doclist->Reload();

            $this->statuspan->setVisible(false);

        }

    }

    //сборка
    public function openedit() {
        $this->editpanel->setVisible(true);
        $this->listpanel->setVisible(false);
        $this->statuspan->setVisible(false);
      
        $this->_doc = Document::load($this->_doc->document_id);
        $this->editpanel->editchat->setAttribute('onclick', "opencchat({$this->_doc->document_id})");

        $ch = $this->checkChat($this->_doc);
        $this->editpanel->editchat->setVisible($ch);
        
        $this->editpanel->editdn->setText($this->_doc->document_number);
        $this->_itemlist = [];
        foreach($this->_doc->unpackDetails('detaildata')  as $it) {

            $it->checked = $it->checked ?? false;
            $it->checkedqty =   0;
            $this->_itemlist[] = $it;

        }

        $this->editpanel->editform->edititemlist->Reload();

    }

  
    
    public function editlistOnRow($row) {
        $item = $row->getDataItem();
        $row->add(new  Label('editlistname', $item->itemname));
        $row->add(new  Label('editlistcode', $item->item_code));
        $row->add(new  Label('editlistbarcode', $item->bar_code));
        $row->add(new  Label('editlistqty', $item->quantity));
        $row->add(new CheckBox('checkscan', new \Zippy\Binding\PropertyBinding($item, 'checked')));

    }

public function addcodeOnClick($sender) {
    $code = trim($this->editpanel->editform->editbarcode->getText());
    $code0 = ltrim($code, '0');  // Убираем ведущие нули

    $this->editpanel->editform->editbarcode->setText('');
    if ($code == '') return;

    // Разделение штрих-кода на item_id и quantity
    $itemId = $code;
    $quantityFromBarcode = 1;

    if (strpos($code, '-') !== false) {
        list($itemId, $quantityFromBarcode) = explode('-', $code);
        $quantityFromBarcode = floatval($quantityFromBarcode);
    }

    foreach ($this->_itemlist as $_item) {
        if ($_item->item_id == $itemId || $_item->bar_code == $code || $_item->bar_code == $code0 || $_item->item_code == $code || $_item->item_code == $code0) {

            $itemName = $_item->itemname ?? 'Товар';

            if ($_item->isweight == 1) {
                if ($_item->checkedqty + $quantityFromBarcode > $_item->quantity) {
                    $this->setError("Лишній ваговий товар: $itemName");
                    return;
                }
                $_item->checkedqty += $quantityFromBarcode;

                $remaining = $_item->quantity - $_item->checkedqty;
                if ($remaining > 0) {
                    $this->setInfo("$itemName додано. Залишилось додати: $remaining");
                }

            } else {
                if ($_item->checkedqty >= $_item->quantity) {
                    $this->setError("Лишній штучний товар: $itemName");
                    return;
                }
                $_item->checkedqty += 1;

                $remaining = $_item->quantity - $_item->checkedqty;
                if ($remaining > 0) {
                    $this->setInfo("$itemName додано. Залишилось додати: $remaining");
                }
            }

            $_item->checked = ($_item->checkedqty == $_item->quantity);

            if ($_item->checked) {
                $this->setSuccess("$itemName повністю зібрано");
            }

            // Обновляем список товаров
            $this->editpanel->editform->edititemlist->Reload();

            // Пауза 0.3 сек после уведомления
            $this->addJavaScript("
                setTimeout(function(){}, 300);
            ");

            // Проверяем, все ли позиции собраны
            if ($this->areAllItemsCollected()) {
                $this->setSuccess('Всі позиції зібрані');
                $this->addJavaScript("
                    setTimeout(function() {
                        $('#editready').click();
                    }, 300);
                ");
            }

            return;
        }
    }

    $this->setWarn('Товар не знайдено');
}



	public function areAllItemsCollected() {
		foreach ($this->_itemlist as $_item) {
			if ($_item->checked != true) {
				return false;
			}
		}
		

        $deliveryStatus = $this->_doc->headerdata['delivery'] ?? '';
        $ocorder = $this->_doc->headerdata['ocorder'] ?? 0;
        
        // Получаем параметры модулей
        $modules = System::getOptions("modules");
        
        // Маппинг delivery => OC статус ID
        $ocStatusMap = [
            1 => 14, // Самовывоз
            2 => 16,
            3 => 16,
            4 => 16,
            5 => 16,
            7 => 16
        ];
        
        // Логирование (отключено)
        // \App\Helper::log('Delivery Status ID: ' . $deliveryStatus);
        // \App\Helper::log('OC Order: ' . $ocorder);
        
        // Если заказ связан с OC и есть подходящий статус
        if ($ocorder > 0 && isset($ocStatusMap[$deliveryStatus])) {
            $ocStatusId = $ocStatusMap[$deliveryStatus];
            $statusMessage = ($deliveryStatus == 1)
                ? 'Очікує у пункті самовивозу'
                : 'Запакований та чекає відправки';
        
            // \App\Helper::log("Обновление OC заказа $ocorder на статус ID: $ocStatusId ({$statusMessage})");
        
            \App\Modules\OCStore\Helper::connect();
        
            $elist = [ $ocorder => $ocStatusId ];
            $data = json_encode($elist);
        
            $fields = [ 'data' => $data ];
            $url = $modules['ocsite'] . '/index.php?route=api/zstore/updateorder&' . System::getSession()->octoken;
        
            $json = \App\Modules\OCStore\Helper::do_curl_request($url, $fields);
            $data = json_decode($json, true);
        
            if (!empty($data['error'])) {
                $data['error'] = str_replace("'", "`", $data['error']);
                $this->setErrorTopPage($data['error']);
                // \App\Helper::log('Ошибка обновления OC статуса: ' . $data['error']);
            } else {
                $this->setSuccess("OC статус оновлено: {$statusMessage}");
                // \App\Helper::log("OC статус успешно обновлён для заказа $ocorder: {$statusMessage} (ID: $ocStatusId)");
            }
        }
        
        // Действия по статусу доставки
        if ($deliveryStatus == 1) {
            if (intval($this->_doc->headerdata['store'] ?? 0) == 0) {
                $sid = H::getDefStore();
                $this->_doc->headerdata['store'] = $sid;
                $st = \App\Entity\Store::load($sid);
                $this->_doc->headerdata['storename'] = $st ? $st->storename : '';
                $this->_doc->save();
            }
            $this->_doc->cast()->reserve();
        } else {
            $this->safeUnreserve();
            App::Redirect("\\App\\Pages\\Doc\\GoodsIssue", 0, $this->_doc->document_id);
        }
        
        return true;


    }

   
    public function editOnSubmit($sender) {

            if ($sender->id == "editcancel") {
                 $this->editpanel->setVisible(false);
                 $this->listpanel->setVisible(true);
                 return;
            }
            $conn = \ZDB\DB::getConnect();
            $conn->BeginTrans();

            try {
 

                foreach ($this->_itemlist as   $_item) {
                    if($sender->id == "editready" && $_item->checked != true) {
                        $this->setError('Не зібрані всі позиції') ;

                        return;
                    }
                }


                $this->_doc->packDetails('detaildata', $this->_itemlist)  ;
                $this->_doc->save();
                if ($sender->id == "editready") {
                    $this->_doc->updateStatus(Document::STATE_READYTOSHIP);
                    $this->listpanel->doclist->Reload(false);

                }
             $conn->CommitTrans();

        } catch(\Exception $e) {
            $this->setError($e->getMessage()) ;
            $conn->RollbackTrans();
            return;
        }
        $this->editpanel->setVisible(false);
        $this->listpanel->setVisible(true);

    }
  
    //разделение
    public function openSplit() {
        $this->splitpanel->setVisible(true);
        $this->listpanel->setVisible(false);
        $this->statuspan->setVisible(false);
      
        $this->_doc = Document::load($this->_doc->document_id);
        
       
        $this->splitpanel->splitdn->setText($this->_doc->document_number);
        $this->_itemlist = [];
        foreach($this->_doc->unpackDetails('detaildata')  as $it) {

            $it->newqty =   0;
      
            $this->_itemlist[] = $it;

        }

        $this->splitpanel->splitform->splititemlist->Reload();
       
    }

    public function splitlistOnRow($row) {
        $item = $row->getDataItem();
        $row->add(new  Label('splitlistname', $item->itemname));
        $row->add(new  Label('splitlistcode', $item->item_code));

        $row->add(new  Label('splitlistqty', $item->quantity));
        $row->add(new TextInput('splitlistnewqty', new \Zippy\Binding\PropertyBinding($item, 'newqty')));

    }
  
    public function splitOnSubmit($sender) {

            if ($sender->id == "splitcancel") {
                 $this->splitpanel->setVisible(false);
                 $this->listpanel->setVisible(true);
                 return;
            }
            
            if($this->_doc->hasPayments() ){
                $this->setError('Документ  з оплатами не  може  бути розділений') ;
                return;
            }
            $ch=$this->_doc->getChildren(); 
            if(is_array($ch) && count($ch)  >0 ){
                $this->setError('Документ  має дочірні') ;
                return;
            }
             
            if($this->_doc->getHD('totaldisc',0)>0 || $this->_doc->getBonus()>0 || $this->_doc->getBonus(false)>0 ){
                $this->setError('Документ  зі знижками  або бонусами не  може  бути розділений') ;
                return;
            }
            
            
            $conn = \ZDB\DB::getConnect();
            $conn->BeginTrans();

            try {
                $newdoc= $this->_doc->cast() ;
                $newdoc->document_id=0;
                $newdoc->document_date=time();
                $newdoc->document_number=$newdoc->nextNumber();;
                $oldlist=[] ;
                $newlist=[] ;
                $totalold=0;
                $totalnew=0;
                
                foreach ($this->_itemlist as   $item) {
                    
                    
                     if($item->newqty > $item->quantity || $item->newqty < 0 )  {
                         $this->setError('Невiрна кiлькiсть для '.$item->itemname) ;
                         return;
                     }
                    
                   
                    $newitem = \App\Entity\Item::load($item->item_id);
                    $newitem->price = $item->price ;
                    $newitem->desc  = $item->desc ;
                    $newitem->quantity  =   $item->newqty  ;
                    $item->quantity  = $item->quantity -  $item->newqty  ;
                     
                    if($newitem->quantity  > 0) {
                      
                       $newlist[]= $newitem;
                       $totalnew += $newitem->price * $newitem->quantity; 
                    } 
                    if($item->quantity  > 0) {   
                       $oldlist[]= $item; 
                       $totalold += $item->price * $item->quantity; 
                    }

                }
                
                if(count($oldlist)==0 || count($newlist)==0)  {
                     $this->setError('Порожній перелік позицій в старому або новому замовленнi ') ;
                     return;
                }
                
                $this->_doc->packDetails('detaildata', $oldlist)  ;
                $this->_doc->amount=$totalold;
                $this->_doc->payamount=$totalold;
                $this->_doc->save();
               
                $newdoc->packDetails('detaildata', $newlist)  ;
                $newdoc->amount=$totalnew;
                $newdoc->payamount=$totalnew;
                $newdoc->save();
                $newdoc->updateStatus(Document::STATE_NEW);
             
                $this->listpanel->doclist->Reload();

               
                $conn->CommitTrans();
                $this->setSuccess('Створено замовдення '.$newdoc->document_number) ;
        } catch(\Exception $e) {
            $this->setError($e->getMessage()) ;
            $conn->RollbackTrans();
            return;
        }
       
        $this->splitpanel->setVisible(false);
        $this->listpanel->setVisible(true);

    }
  
    //vue

    /**
    * список  ТМЦ в  заказе
    *
    * @param mixed $args
    */
    public function getCChatItems($args) {
        $doc = Document::load($args[0]) ;
        $ret=[];
        $ret['itemlist'] =[];
        foreach ($doc->unpackDetails('detaildata') as $item) {
            $ret['itemlist'][] = array(
              'itemname'=>$item->itemname,
              'item_code'=>$item->item_code,
              'quantity'=>H::fqty($item->quantity),
              'price'=> H::fa($item->price)
            ) ;

        }
      
        return  $this->jsonOK($ret) ;

    }

    /**
    * список  сообщений по  заказу
    *
    * @param mixed $args[0]  -document_id
    */
    public function getCChatMessages($args) {

        $ret=[];
        $list = \App\Entity\Message::find("item_id={$args[0]} and item_type=" .\App\Entity\Message::TYPE_CUSTCHAT, "message_id asc");

        $ret['msglist'] = [];

        foreach($list as $msg) {
            $m=[];
            $m['isseller']  = $msg->user_id >0;
            $m['message']  = $msg->message;
            $m['checked']  = $msg->checked==1;
            $m['msgdate'] = date('Y-m-d H:i', $msg->created);


            $ret['msglist'][] = $m;

            if(!$m['isseller']) {
                $msg->checked = 1;
                $msg->save();
            }


        }

       return $this->jsonOK($ret) ;
   
    }
    /**
    * отправка сообшения заказчику
    *
    * @param mixed $args
    */
    public function sendMessage($args, $post) {
        $doc = Document::load($args[0]) ;
        $message = json_decode($post)   ;

        $issms = (\App\System::getOption('sms', 'smstype')??0) >0 ;
        if($issms == 0) {
           return  $this->jsonError("Не знайдений сервіс смс") ;
        }

        $phone= $doc->headerdata['phone'] ??'';
        if($phone=='') {
            $c =  \App\Entity\Customer::load($doc->customer_id) ;
            $phone = $c->phone ?? '';
        }
        if($phone == '') {
           return  $this->jsonError("Не знайдений телефон") ;

        }

        $link = _BASEURL . 'cchat/' . $args[0]. '/'. $doc->headerdata['hash'];
       
        $fn = (\App\System::getOption('common', 'shopname')??'')  ;

        $text = "Маємо запитання  по  вашому  замовленню. Відповісти за адресою ".$link;

        $r = \App\Comm::sendSMS($phone, $text) ;
        if($r!="") {
          return  $this->jsonError($r) ;
        }

        $msg = new \App\Entity\Message() ;
        $msg->message=$message;
        $msg->user_id= \App\System::getUser()->user_id;
        $msg->item_id=$doc->document_id;
        $msg->item_type=\App\Entity\Message::TYPE_CUSTCHAT;
        $msg->save() ;

        return $this->jsonOK() ;
             

    }

    /**
     * Друк етикеток під розлив.
     *
     * remaining = фізичний − qty_замовлення
     * deficit   = скільки розлити, щоб закрити замовлення
     * якщо remaining < minqty → долити до recom (або до minqty, якщо recom порожній)
     * комплектуючі (ItemSet) ріжуть тираж; наклейка/флакон ліміт не ставлять
     *
     * Зарезервоване: getQuantity() вже без qty. Автооприбуткування = додатні TAG_RESERV.
     * Ваговий: шматки по 1000, штрих-код item_id-qty, без min/recom.
     */
    public function printlabels($sender) {

        $items = [];
        $storeId = 32;         // основний склад
        $componentStore = 32;
        $reserved = $this->docHasReserve($this->_doc);

        $partPool = [];

        foreach ($this->_doc->unpackDetails('detaildata') as $it) {

            $cat = \App\Entity\Item::load($it->item_id);
            if ($cat == null) {
                continue;
            }

            if (intval($cat->noprint) == 1) {
                continue;
            }

            $orderQty = doubleval($it->quantity);
            if ($orderQty <= 0) {
                continue;
            }

            if (intval($cat->isweight) == 1) {
                $left = $orderQty;
                while ($left > 1000) {
                    $item = clone $it;
                    $item->quantity = 1000;
                    $item->printqty = 1;
                    $item->bar_code = $item->item_id . "-" . sprintf("%02d", $item->quantity);
                    $items[] = $item;
                    $left -= 1000;
                }
                if ($left > 0) {
                    $item = clone $it;
                    $item->quantity = $left;
                    $item->printqty = 1;
                    $item->bar_code = $item->item_id . "-" . sprintf("%02d", $item->quantity);
                    $items[] = $item;
                }
                continue;
            }

            $stock = doubleval($cat->getQuantity($storeId));
            $autoQty = $reserved ? $this->getAutoIncomeQty($cat->item_id) : 0;
            $fromShelf = $reserved ? max(0, $orderQty - $autoQty) : 0;
            $physicalNow = $stock + $fromShelf;

            $remaining = $physicalNow - $orderQty;
            $deficit = max(0, -$remaining);
            $remainingAfter = max(0, $remaining);

            $minQty = doubleval($cat->minqty);
            $recom = $this->getRecomQty($cat);

            $buffer = 0;
            if ($minQty > 0 && $remainingAfter < $minQty) {
                $target = $recom > 0 ? $recom : $minQty;
                $buffer = max(0, $target - $remainingAfter);
            }

            $desired = $deficit + $buffer;
            if ($desired <= 0) {
                H::log("Етикетки {$cat->itemname}: склад покриває, remaining={$remainingAfter}");
                continue;
            }

            $canMake = $this->canMakeFromBom($cat->item_id, $componentStore, $partPool);
            $toPrint = $desired;
            if ($canMake !== null) {
                if ($canMake <= 0) {
                    H::log("Етикетки {$cat->itemname}: немає комплектуючих, треба {$desired}");
                    continue;
                }
                if ($toPrint > $canMake) {
                    H::log("Етикетки {$cat->itemname}: треба {$desired}, комплектуючих на {$canMake}");
                    $toPrint = $canMake;
                }
                $this->takeBom($cat->item_id, $toPrint, $componentStore, $partPool);
            }

            $item = clone $it;
            $item->quantity = $toPrint;
            $item->printqty = $toPrint;
            $item->bar_code = $cat->bar_code;
            $items[] = $item;

            H::log("Етикетки {$cat->itemname} qty={$toPrint} (дефіцит={$deficit} буфер={$buffer})");
        }

        if (empty($items)) {
            $this->addAjaxResponse("toastr.warning('Нема данних для друку')");
            return;
        }

        $user = \App\System::getUser();
        $ret = H::printItems($items);

        if (intval($user->prtypelabel) === 0) {
            if ($user->usemobileprinter == 1) {
                \App\Session::getSession()->printform = $ret;
                $this->addAjaxResponse("window.open('/index.php?p=App/Pages/ShowReport&arg=print')");
            } else {
                $this->addAjaxResponse("$('#tag').html('{$ret}'); $('#pform').modal()");
            }
        }

        try {
            $buf = null;
            if (intval($user->prtypelabel) === 1 && strlen($ret) > 0) {
                $buf = \App\Printer::xml2comm($ret);
            } elseif (intval($user->prtypelabel) === 2 && count($ret) > 0) {
                $buf = \App\Printer::arr2comm($ret);
            }
            if ($buf !== null) {
                $this->addAjaxResponse("sendPSlabel('" . json_encode($buf) . "')");
            }
        } catch (\Exception $e) {
            $msg = str_replace([";", "'"], "`", $e->getMessage());
            $this->addAjaxResponse("toastr.error('{$msg}')");
        }
    }

    /** Рекомендована кількість з кастомного поля `recom`. */
    private function getRecomQty($item) {
        if (method_exists($item, 'getcf')) {
            $cfs = $item->getcf(true);
            if (is_array($cfs)) {
                foreach ($cfs as $k => $v) {
                    $code = is_object($v) ? ($v->code ?? '') : $k;
                    $val = is_object($v) ? ($v->val ?? 0) : $v;
                    if ($code === 'recom') {
                        return doubleval($val);
                    }
                }
            }
        }
        $raw = $item->cflist ?? '';
        if (is_string($raw) && $raw !== '') {
            $cf = @unserialize($raw);
            if (is_array($cf) && isset($cf['recom'])) {
                return doubleval($cf['recom']);
            }
        }
        if (!empty($item->detail) && is_string($item->detail)) {
            if (preg_match('/<cflist><!\[CDATA\[(.*?)\]\]><\/cflist>/s', $item->detail, $m)
                || preg_match('/<cflist>(.*?)<\/cflist>/s', $item->detail, $m)) {
                $cf = @unserialize($m[1]);
                if (is_array($cf) && isset($cf['recom'])) {
                    return doubleval($cf['recom']);
                }
            }
        }
        return 0;
    }

    /**
     * Скільки Zippy оприбуткувала при резерві.
     * Додатні проводки TAG_RESERV по цьому товару в цьому документі.
     */
    private function getAutoIncomeQty($itemId) {
        $conn = \ZDB\DB::getConnect();
        $docId = intval($this->_doc->document_id);
        $itemId = intval($itemId);
        $tag = \App\Entity\Entry::TAG_RESERV;
        $sql = "SELECT COALESCE(SUM(e.quantity),0)
                FROM entrylist e
                JOIN store_stock s ON e.stock_id = s.stock_id
                WHERE e.document_id = {$docId}
                  AND e.tag = {$tag}
                  AND s.item_id = {$itemId}
                  AND e.quantity > 0";
        return doubleval($conn->GetOne($sql));
    }

    private function isIgnorablePart($name) {
        $n = mb_strtolower($name);
        return (mb_strpos($n, 'наклейка') !== false) || (mb_strpos($n, 'флакон') !== false);
    }

    /**
     * Максимум з поточних залишків комплектуючих.
     * null = комплектації немає, не обмежуємо.
     */
    private function canMakeFromBom($itemId, $componentStore, &$partPool) {
        $parts = \App\Entity\ItemSet::find("pitem_id=" . intval($itemId));
        if (!is_array($parts) && !($parts instanceof \Traversable)) {
            return null;
        }

        $limit = null;
        foreach ($parts as $part) {
            $pi = \App\Entity\Item::load($part->item_id);
            if (!$pi || doubleval($part->qty) <= 0) {
                continue;
            }
            if ($this->isIgnorablePart($pi->itemname)) {
                continue;
            }
            $pid = intval($pi->item_id);
            if (!isset($partPool[$pid])) {
                $partPool[$pid] = doubleval($pi->getQuantity($componentStore));
            }
            $can = floor($partPool[$pid] / doubleval($part->qty));
            $limit = $limit === null ? $can : min($limit, $can);
        }
        return $limit;
    }

    private function takeBom($itemId, $toPrint, $componentStore, &$partPool) {
        $parts = \App\Entity\ItemSet::find("pitem_id=" . intval($itemId));
        foreach ($parts as $part) {
            $pi = \App\Entity\Item::load($part->item_id);
            if (!$pi || doubleval($part->qty) <= 0) {
                continue;
            }
            if ($this->isIgnorablePart($pi->itemname)) {
                continue;
            }
            $pid = intval($pi->item_id);
            if (!isset($partPool[$pid])) {
                $partPool[$pid] = doubleval($pi->getQuantity($componentStore));
            }
            $partPool[$pid] -= doubleval($part->qty) * $toPrint;
        }
    }

}

/**
 *  Источник  данных  для   списка  документов
 */
class OrderDataSource implements \Zippy\Interfaces\DataSource
{
    private $page;

    public function __construct($page) {
        $this->page = $page;
    }

    private function getWhere() {
        $user = System::getUser();
       
        $conn = \ZDB\DB::getConnect();
        $filter=$this->page->listpanel->filter;

          
        $where = "     meta_name  = 'Order'    ";
      

        $salesource =$filter->salesource->getValue();
        if ($salesource > 0) {
            $where .= " and   content like '%<salesource>{$salesource}</salesource>%'  ";

        }

        $status = $filter->status->getValue();
        if ($status == 0) {
            $where .= " and  state not in (9,17,15)   ";
        }
        if ($status == 1) {
            $where .= " and  state =1 ";
        }
        if ($status == 2) {
            $where .= " and   (state = 21 or content like '%<waitpay>1</waitpay>%') ";
        }


        $st = trim($filter->searchtext->getText());
        if (strlen($st) > 2) {
            $st = $conn->qstr('%' . $st . '%');

            $where = "  meta_name  = 'Order'  and  content like {$st} ";
        }
        $sn = trim($filter->searchnumber->getText());
        if (strlen($sn) > 1) { // игнорируем другие поля
            $sn = $conn->qstr('%' . $sn . '%');
            $where = "  meta_name  = 'Order' and  document_number like  {$sn} ";
        }

        return $where;
    }

    public function getItemCount() {
        return Document::findCnt($this->getWhere());
    }

    public function getItems($start, $count, $sortfield = null, $asc = null) {
        $docs = Document::find($this->getWhere(), "document_id desc", $count, $start);
        //         $docs = Document::find($this->getWhere(), "priority desc,document_id desc", $count, $start);

        return $docs;
    }

    public function getItem($id) {

    }

}
