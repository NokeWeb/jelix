<?php
/**
* @package     jelix
* @subpackage  forms
* @author      Laurent Jouanneau
* @contributor
* @copyright   2006-2007 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/


/**
 * exception for jforms
 */

class jExceptionForms extends jException {

}

/**
 * base class of all form classes generated by the jform compiler
 * @package     jelix
 * @subpackage  forms
 */
abstract class jFormsBase {

    /**
     * List of all form controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $_controls = array();

    /**
     * List of submit buttons
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $_submits = array();

    /**
     * List of uploads controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $_uploads = array();

    /**
     * the datas container
     * @var jFormsDataContainer
     */
    protected $_container=null;

    /**
     * says if the form is readonly
     * @var boolean
     */
    protected $_readOnly = false;

    /**
     * content list of available form builder
     * @var boolean
     */
    protected $_builders = array();

    /**
     * the form selector
     * @var string
     */
    protected $_sel;

    /**
     * @param string $sel the form selector
     * @param jFormsDataContainer $container the datas container
     * @param boolean $reset says if the datas should be reset
     */
    public function __construct($sel, &$container, $reset = false){
        $this->_container = & $container;
        if($reset){
            $this->_container->clear();
        }
        $this->_sel = $sel;
    }

    /**
     * set form datas from request parameters
     */
    public function initFromRequest(){
        $req = $GLOBALS['gJCoord']->request;
        foreach($this->_controls as $name=>$ctrl){
            $value = $req->getParam($name);
            //@todo à prevoir un meilleur test, pour les formulaires sur plusieurs pages
            if($value === null) $value='';
            if($ctrl->type=='checkbox'){
                if($value){
                    $this->_container->datas[$name]= $ctrl->valueOnCheck;
                }else{
                    $this->_container->datas[$name]= $ctrl->valueOnUncheck;
                }
            }elseif($ctrl->type=='upload'){
                if(isset($_FILES[$name])){
                    $this->_container->datas[$name]= $_FILES[$name]['name'];
                }else{
                    $this->_container->datas[$name]= '';
                }
            }else{
                $this->_container->datas[$name]= $value;
            }
        }
    }

    /**
     * check validity of all datas form
     * @return boolean true if all is ok
     */
    public function check(){
        $this->_container->errors = array();
        foreach($this->_controls as $name=>$ctrl){
            $err = $ctrl->check($this->_container->datas[$name], $this);
            if($err !== null)
                $this->_container->errors[$name]= $err;
        }
        return count($this->_container->errors) == 0;
    }

    /**
     * set form datas from a DAO
     * @param string $daoSelector the selector of a dao file
     * @param string $key the primary key for the dao. if null, takes the form ID as primary key
     * @param string $dbProfil the jDb profil to use with the dao
     * @see jDao
     */
    public function initFromDao($daoSelector, $key = null, $dbProfil=''){
        if($key === null)
            $key = $this->_container->formId;
        $dao = jDao::create($daoSelector, $dbProfil);
        $daorec = $dao->get($key);
        if(!$daorec) {
            if(is_array($key)) $key = var_export($key,true);
            throw new jExceptionForms('jelix~formserr.bad.formid.for.dao', array($daoSelector, $key, $this->_sel));
        }

        $prop = $dao->getProperties();
        foreach($this->_controls as $name=>$ctrl){
            if(isset($prop[$name])) {
                if($ctrl->datatype instanceof jDatatypeLocaleDateTime && $prop[$name]['datatype'] == 'datetime') {
                    $dt = new jDateTime();
                    $dt->setFromString($daorec->$name, jDateTime::DB_DTFORMAT);
                    $this->_container->datas[$name] = $dt->toString(jDateTime::LANG_DTFORMAT);
                }elseif($ctrl->datatype instanceof jDatatypeLocaleDate && $prop[$name]['datatype'] == 'date') {
                    $dt = new jDateTime();
                    $dt->setFromString($daorec->$name, jDateTime::DB_DFORMAT);
                    $this->_container->datas[$name] = $dt->toString(jDateTime::LANG_DFORMAT);
                }else{
                    $this->_container->datas[$name] = $daorec->$name;
                }
            }
        }
    }

    /**
     * save datas using a dao.
     * it call insert or update depending the value of the formId stored in the container
     * @param string $daoSelector the selector of a dao file
     * @param string $key the primary key for the dao. if null, takes the form ID as primary key
     * @param string $dbProfil the jDb profil to use with the dao
     * @return mixed  the primary key of the new record in a case of inserting
     * @see jDao
     */
    public function saveToDao($daoSelector, $key = null, $dbProfil=''){
        $dao = jDao::create($daoSelector, $dbProfil);
        $daorec = jDao::createRecord($daoSelector, $dbProfil);
        $prop = $dao->getProperties();
        foreach($this->_controls as $name=>$ctrl){
            if(is_array($this->_container->datas[$name])){
                if( count ($this->_container->datas[$name]) ==1){
                    $daorec->$name = $this->_container->datas[$name][0];
                }else{
                    // do nothing for arrays ?
                }
            }else{
                $daorec->$name = $this->_container->datas[$name];
            }

            if($ctrl->datatype instanceof jDatatypeLocaleDateTime && $prop[$name]['datatype'] == 'datetime') {
                $dt = new jDateTime();
                $dt->setFromString($daorec->$name, jDateTime::LANG_DTFORMAT);
                $daorec->$name = $dt->toString(jDateTime::DB_DTFORMAT);
            }elseif($ctrl->datatype instanceof jDatatypeLocaleDate && $prop[$name]['datatype'] == 'date') {
                $dt = new jDateTime();
                $dt->setFromString($daorec->$name, jDateTime::LANG_DFORMAT);
                $daorec->$name = $dt->toString(jDateTime::DB_DFORMAT);
            }
        }
        if($this->_container->formId){
            if($key === null)
                $key = $this->_container->formId;
            $daorec->setPk($key);
            if($dao->update($daorec) == 0)
                $dao->insert($daorec);
        }else{
            if($key !== null)
                $daorec->setPk($key);
            // todo : what about updating the formId with the Pk.
            $dao->insert($daorec);
        }
        return $daorec->getPk();
    }

    /**
     * set datas from a DAO, in a control
     * 
     * The control must be a container like checkboxes or listbox with multiple attribute.
     * The form should contain a formId
     *
     * The Dao should map to an "association table" : its primary key should be composed by
     * the primary key stored in the formId (or the given primarykey) + the field which will contain one of 
     * the values of the control. If this order is not the same as defined into the dao,
     * you should provide the list of property names which corresponds to the primary key
     * in this order : properties for the formId, followed by the property which contains 
     * the value.
     * @param string $controlName  the name of the control
     * @param string $daoSelector the selector of a dao file
     * @param mixed  $primaryKey the primary key if the form have no id. (optional)
     * @param mixed  $primaryKeyNames list of field corresponding to primary keys (optional)
     * @param string $dbProfil the jDb profil to use with the dao
     * @see jDao
     */
    public function initControlFromDao($controlName, $daoSelector, $primaryKey = null, $primaryKeyNames=null, $dbProfil=''){

        if(!$this->_controls[$controlName]->isContainer()){
            throw new jExceptionForms('jelix~formserr.control.not.container', array($controlName, $this->_sel));
        }

        if(!$this->_container->formId)
            throw new jExceptionForms('jelix~formserr.formid.undefined.for.dao', array($controlName, $this->_sel));

        if($primaryKey === null)
            $primaryKey = $this->_container->formId;

        if(!is_array($primaryKey))
            $primaryKey =array($primaryKey);

        $dao = jDao::create($daoSelector, $dbProfil);
        $daorec = jDao::createRecord($daoSelector, $dbProfil);

        $conditions = jDao::createConditions();
        if($primaryKeyNames)
            $pkNamelist = $primaryKeyNames;
        else
            $pkNamelist = $dao->getPrimaryKeyNames();

        foreach($primaryKey as $k=>$pk){
            $conditions->addCondition ($pkNamelist[$k], '=', $pk);
        }

        $results = $dao->findBy($conditions);
        $valuefield = $pkNamelist[$k+1];
        $val = array();
        foreach($results as $res){
            $val[]=$res->$valuefield;
        }
        $this->_container->datas[$controlName]=$val;
    }


    /**
     * save datas of a control using a dao.
     *
     * The control must be a container like checkboxes or listbox with multiple attribute.
     * If the form contain a new record (no formId), you should call saveToDao before
     * in order to get a new id (the primary key of the new record), or you should get a new id 
     * by an other way. then you must pass this primary key in the third argument.
     * If the form have already a formId, then it will be used as a primary key, unless
     * you give one in the third argument.
     *
     * The Dao should map to an "association table" : its primary key should be
     * the primary key stored in the formId + the field which will contain one of 
     * the values of the control. If this order is not the same as defined into the dao,
     * you should provide the list of property names which corresponds to the primary key
     * in this order : properties for the formId, followed by the property which contains 
     * the value.
     * All existing records which have the formid in their keys are deleted
     * before to insert new values.
     *
     * @param string $controlName  the name of the control
     * @param string $daoSelector the selector of a dao file
     * @param mixed  $primaryKey the primary key if the form have no id. (optional)
     * @param mixed  $primaryKeyNames list of field corresponding to primary keys (optional)
     * @param string $dbProfil the jDb profil to use with the dao
     * @see jDao
     */
    public function saveControlToDao($controlName, $daoSelector, $primaryKey = null, $primaryKeyNames=null, $dbProfil=''){

        if(!$this->_controls[$controlName]->isContainer()){
            throw new jExceptionForms('jelix~formserr.control.not.container', array($controlName, $this->_sel));
        }

        $values = $this->_container->datas[$controlName];
        if(!is_array($values))
            throw new jExceptionForms('jelix~formserr.value.not.array', array($controlName, $this->_sel));

        if(!$this->_container->formId && !$primaryKey)
            throw new jExceptionForms('jelix~formserr.formid.undefined.for.dao', array($controlName, $this->_sel));

        if($primaryKey === null)
            $primaryKey = $this->_container->formId;

        if(!is_array($primaryKey))
            $primaryKey =array($primaryKey);

        $dao = jDao::create($daoSelector);
        $daorec = jDao::createRecord($daoSelector);

        $conditions = jDao::createConditions();
        if($primaryKeyNames)
            $pkNamelist = $primaryKeyNames;
        else
            $pkNamelist = $dao->getPrimaryKeyNames();

        foreach($primaryKey as $k=>$pk){
            $conditions->addCondition ($pkNamelist[$k], '=', $pk);
            $daorec->{$pkNamelist[$k]} = $pk;
        }

        $dao->deleteBy($conditions);

        $valuefield = $pkNamelist[$k+1];
        foreach($values as $value){
            $daorec->$valuefield = $value;
            $dao->insert($daorec);
        }
    }

    /**
     * set the form  read only or read/write
     * @param boolean $r true if you want read only
     */
    public function setReadOnly($r = true){  $this->_readOnly = $r;  }

    /**
     * return list of errors found during the check
     * @return array
     * @see jFormsBase::check
     */
    public function getErrors(){  return $this->_container->errors;  }

    /**
     * set an error message on a specific field
     * @param string $field the field name
     * @param string $mesg  the error message string 
     */
    public function setErrorOn($field, $mesg){
        $this->_container->errors[$field]=$mesg;
    }

    /**
     *
     * @param string $name the name of the control/data
     * @param string $value the data value
     */
    public function setData($name,$value){
        if($this->_controls[$name]->type == 'checkbox') {
            if($value != $this->_controls[$name]->valueOnCheck){
                if($value =='on')
                    $value = $this->_controls[$name]->valueOnCheck;
                else
                    $value = $this->_controls[$name]->valueOnUncheck;
            }
        }
        $this->_container->datas[$name]=$value;
    }
    /**
     *
     * @param string $name the name of the control/data
     * @return string the data value
     */
    public function getData($name){
        if(isset($this->_container->datas[$name]))
            return $this->_container->datas[$name];
        else return null;
    }

    /**
     * @return array form datas
     */
    public function getDatas(){ return $this->_container->datas; }
    /**
     * @return jFormsDataContainer
     */
    public function getContainer(){ return $this->_container; }

    /**
     * @return array of jFormsControl objects
     */
    public function getControls(){ return $this->_controls; }

    /**
     * @return array of jFormsControl objects
     */
    public function getSubmits(){ return $this->_submits; }

    /**
     * @return string the formId
     */
    public function id(){ return $this->_container->formId; }

    /**
     * @return boolean
     */
    public function hasUpload() { return count($this->_uploads)>0; }

    /**
     * @param string $buildertype  the type name of a form builder
     * @param string $action action selector where form will be submit
     * @param array $actionParams  parameters for the action
     * @return jFormsBuilderBase
     */
    public function getBuilder($buildertype, $action, $actionParams){
        if(isset($this->_builders[$buildertype])){
            include_once(JELIX_LIB_FORMS_PATH.'jFormsBuilderBase.class.php');
            include_once ($this->_builders[$buildertype][0]);
            $c =  $this->_builders[$buildertype][1];
            return new $c($this, $action, $actionParams);
        }else{
            throw new jExceptionForms('jelix~formserr.invalid.form.builder', array($buildertype, $this->_sel));
        }
    }

    /**
     * save an uploaded file in the given directory. the given control must be 
     * an upload control of course.
     * @param string $controlName the name of the upload control
     * @param string $path path of the directory where to store the file. If it is not given,
     *                     it will be stored under the var/uploads/_modulename~formname_/ directory
     * @param string $alternateName a new name for the file. If it is not given, the file
     *                              while be stored with the original name
     * @return boolean true if the file has been saved correctly
     */
    public function saveFile($controlName, $path='', $alternateName='') {
        if ($path == '') {
            $path = JELIX_APP_VAR_PATH.'uploads/'.$this->_sel.'/';
        } else if (substr($path, -1, 1) != '/') {
            $path.='/';
        }

        if(!isset($this->_controls[$controlName]) || $this->_controls[$controlName]->type != 'upload')
            throw new jExceptionForms('jelix~formserr.invalid.upload.control.name', array($controlName, $this->_sel));

        if(!isset($_FILES[$controlName]) || $_FILES[$controlName]['error']!= UPLOAD_ERR_OK)
            return false;

        if($this->_controls[$controlName]->maxsize && $_FILES[$controlName]['size'] > $this->_controls[$controlName]->maxsize){
            return false;
        }
        jFile::createDir($path);
        if ($alternateName == '') {
            $path.= $_FILES[$controlName]['name'];
        } else {
            $path.= $alternateName;
        }
        move_uploaded_file($_FILES[$controlName]['tmp_name'], $path);
        return true;
    }

    /**
     * save all uploaded file in the given directory
     * @param string $path path of the directory where to store the file. If it is not given,
     *                     it will be stored under the var/uploads/_modulename~formname_/ directory
     */
    public function saveAllFiles($path='') {
        if ($path == '') {
            $path = JELIX_APP_VAR_PATH.'uploads/'.$this->_sel.'/';
        } else if (substr($path, -1, 1) != '/') {
            $path.='/';
        }

        if(count($this->_uploads))
            jFile::createDir($path);

        foreach($this->_uploads as $ref=>$ctrl){

            if(!isset($_FILES[$ref]) || $_FILES[$ref]['error']!= UPLOAD_ERR_OK)
                continue;
            if($ctrl->maxsize && $_FILES[$ref]['size'] > $ctrl->maxsize)
                continue;

            move_uploaded_file($_FILES[$ref]['tmp_name'], $path.$_FILES[$ref]['name']);
        }
    }


    /**
    * add a control to the form
    * @param $control jFormsControl
    */
    protected function addControl($control){
        $this->_controls [$control->ref] = $control;
        if($control->type =='submit')
            $this->_submits [$control->ref] = $control;
        if($control->type =='upload'){
            $this->_uploads [$control->ref] = $control;
        }
        if(!isset($this->_container->datas[$control->ref])){
            $this->_container->datas[$control->ref] = $control->defaultValue;
        }
    }
}

?>
