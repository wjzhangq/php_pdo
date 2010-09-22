<?php
/**
 * pdo 的扩展类
 * author: wjzhangq <wjzhangq@126.com>
 */
 
class db implements ArrayAccess, Countable
{
    protected $tables; //db中所有表
    protected $pdo;
    
    //初始化
    function __construct($dsn, $username=null, $password=null, $driver_options=null)
    {
        $this->pdo = new PDO($dsn, $username, $password, $driver_options);
        
        $this->init_tables();
    }
    
    //implements ArrayAccess
    function offsetExists($offset)
    {
        return isset($this->tables[$offset]);
    }
    
    function offsetGet($offset)
    {
        $ret_val = null;
        if (isset($this->tables[$offset]))
        {
            if (!is_object($this->tables[$offset]))
            {
                $this->tables[$offset] = new db_table($this, $offset);
            }
            
            $ret_val = $this->tables[$offset];
        }
        
        return $ret_val;
    }
    
    function offsetSet($offset, $value)
    {
        //nothing
        //todo: add new table
    }
    
    function offsetUnset($offset)
    {
        //TRUNCATE a table
        self::query('TRUNCATE TABLE ' . $offset);
    }
    
    //implements Countable
    function count()
    {
        return count($this->tables);
    }
    
    //common function
    /*获取唯一元素*/
    function getOne()
    {
        $args = func_get_args();
        
        //加limit
        if (stripos($args[0], 'limit') === false)
        {
            $args[0] .= ' LIMIT 1';
        }
        
        $sth = $this->query($args);
        
        return $sth->fetchColumn(); 
    }
    
    /*获取一列元素*/
    function getCol()
    {
        $args = func_get_args();

        $sth = $this->query($args);
        
        return $sth->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    /*获取一行元素*/
    function getRow()
    {
        $args = func_get_args();
        
        //加limit
        if (stripos($args[0], 'limit') === false)
        {
            $args[0] .= ' LIMIT 1';
        }
        
        $sth = $this->query($args);
        
        return $sth->fetch(PDO::FETCH_ASSOC);
    }
    
    /*获取一组元素*/
    function getAll()
    {
        $args = func_get_args();
        $sth = $this->query($args);
        
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    //修正函数
    function query()
    {
        $args = func_get_args();

        if (!isset($args[0]))
        {
            throw new Exception('less param for query', 10);
        }

        switch (count($args))
        {
            case 1:
                if (is_array($args[0]))
                {
                    $sql = $args[0][0];
                    $param = isset($args[0][1]) ? $args[0][1] : array();
                }
                elseif(is_string($args[0]))
                {
                    $sql = $args[0];
                    $param = array();
                }
                else
                {
                    throw  new Exception ('Unsuport param for query!', 10);
                }
                break;
            case 2:
                $sql = $args[0];
                $param = $args[1];
                break;
            default:
                throw  new Exception ('Unsuport param for query!', 10);
                break;
        }

        if ($param)
        {
            $sth = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            if (!$sth){
                $errorInfo = $this->pdo->errorInfo();
                throw new Exception("Error sql query:$sql\n" . $errorInfo[2], 10);  
            }
            if (!$sth->execute($param))
            {
                $error_info = $sth->errorInfo();
                throw new Exception("Error sql prepare:$sql\n" . $error_info[2], 10);
            }
        }
        else
        {
            if (!$sth = $this->pdo->query($sql))
            {
                $errorInfo = $this->pdo->errorInfo();
                throw new Exception("Error sql query:$sql\n" . $errorInfo[2], 10);
            }
        }
        
        return $sth;
    }
    
    //私有函数
    private function init_tables()
    {
        $col = $this->getCol("SHOW TABLES");
        $this->tables = array_flip($col);
    }
}


class db_table implements ArrayAccess, Countable
{
    protected $table_name; // 表名称
    protected $table_fileds; //表字段
    protected $table_key; //表主键
    protected $db; //db
    
    protected $limit = array(); //限制
    protected $order = array(); //排序
    protected $where = array(); //where 条件
    
    function __construct(&$db, $table_name)
    {
        $this->db= $db; //引用db
        $this->table_name = $table_name;
        
        //获取字段
        $sql = 'DESC `'.$this->table_name.'`';
        $tmp = $db->getCol($sql);
        foreach ($tmp as $v)
        {
            $this->table_fileds[$v] = ':' . $v;
        }
        
        //获取主键
        $sql = 'SHOW INDEX FROM `' . $this->table_name . '` WHERE Key_name = \'PRIMARY\'#no limit';
        $tmp = $db->getRow($sql);
        $this->table_key = isset($tmp['Column_name']) ? $tmp['Column_name'] : '';        
    }
    
    //插入数据
    function insert($data)
    {
        $this->offsetSet(null, $data);
 
        return $this->table_key ? $this->db->lastInsertId() : null; //有主键返回主键值
    }
    
    //替换
    function replace($data){
        $set = $this->build_query($data);
        if ($set){
            $sql = 'REPLACE INTO `'.$this->table_name.'` SET ' . implode(', ', $set[0]);

            $this->db->query($sql, $set[1]);

            return $this->table_key ? $this->db->lastInsertId() : null; //有主键返回主键值
        }
        
        return null;

    }
    
    //查询
    function query($where){
        $where = $this->offset_parse($where);
        $order = $this->get_order();
        $limit = $this->get_limit();
        
        $sql = 'SELECT * FROM `'. $this->table_name .'` WHERE ' . $where . $order . $limit;
        
        if ($limit){
            return $this->db->getAll($sql);
        }else{
            return $this->db->getRow($sql);
        }
    }
    
    //where 条件
    function where($where=array()){
        $this->where = $where;
        return $this;
    }
    
    //设置排序
    function order(){
        $argv = func_get_args();
        $list = array();
        if ($argv){
            foreach($argv as $v){
                $tmp = explode(' ', $v);
                if (count($tmp) == 2){
                    $order = trim($tmp[0]);
                    if (!isset($this->table_fileds[$order])){
                        continue;
                    }
                    $by = strtoupper(trim($tmp[1])) == 'DESC' ? 'DESC' : 'ASC';
                    $list[] = '`' . $order . '` ' . $by;
                }
            }
        }
        
        if ($list){
            $this->order = $list;
        }

        return $this;
    }
    
    //设置limit
    function limit(){
        $argv = func_get_args();
        $limit = array();
        switch(count($argv)){
            case 1:
                $limit = array(0, $argv[0]);
                break;
            case 2:
                $limit = $argv;
                break;
            default:
                break;
        }
        
        if ($limit){
            $this->limit = $limit;
        }
        return $this;
    }
    
    //获取排序
    protected function get_order(){
        $list = array();
        if ($this->order){
            $list = $this->order;
            $this->order = array();
        }
        
        return $list ? ' ORDER BY ' . implode(',', $list) : '';
    }
    
    //获取limit
    protected function get_limit(){
        $str = '';
        if ($this->limit){
            $str = ' LIMIT ' . $this->limit[0] . ', ' . $this->limit[1];
            $this->limit = array();
        }
        
        return $str;
    }
    
    
    //implements
    function offsetExists($offset)
    {
        $where = $this->offset_parse($offset);
        
        $sql = 'SELECT COUNT(*) FROM `' .$this->table_name. '` WHERE ' . $where;
        
        return (bool) $this->db->getOne($sql);
        
    }
    
    function offsetGet($offset)
    {
        return $this->query($offset);
    }
    
    function offsetSet($offset, $value)
    {
        $set = $this->build_query($value);
        if ($set)
        {
            if (isset($offset))
            {
                $where = $this->offset_parse($offset);
                $sql = 'UPDATE `'. $this->table_name . '` SET ' . implode(', ', $set[0]) . ' WHERE ' . $where;
            }
            else
            {
                $sql = 'INSERT INTO `'.$this->table_name.'` SET ' . implode(', ', $set[0]);
            }
            $this->db->query($sql, $set[1]);
        }
    }
    
    function offsetUnset($offset)
    {
        $where = $this->offset_parse($offset);
        $sql = 'DELETE FROM `'.$this->table_name.'` WHERE ' . $where;

        return $this->db->query($sql);
    }
    
    function count()
    {
        $where = '';
        if ($this->where){
            $where = $this->offset_parse($this->where);
            $this->where = array();
        }
        
        $sql = 'SELECT COUNT(*) FROM `'.$this->table_name.'`';
        if ($where){
            $sql .= ' WHERE ' . $where;
        }
        
        return $this->db->getOne($sql);
    }
    
    //私有函数
    private function offset_parse($offset)
    {
        $where = '';
        switch (gettype($offset))
        {
            case 'integer':
            case 'string':
            case 'double':
                if ($this->table_key)
                {
                    $where = '`' . $this->table_key . '`= \'' . addcslashes($offset, '\'') . '\'';
                }
                break;
            case 'array':
                $set = array();
                foreach ($offset as $key => $val)
                {
                    $tmp = explode(':', $key, 2);
                    $key = $tmp[0];
                    $opt = '=';
                    if (isset($tmp[1])){
                        if (in_array($tmp[1], array('=', '<', '<=', '>', '>='))){
                            $opt = $tmp[1];
                        }
                    }
                    switch(gettype($val))
                    {
                        case 'array':
                            $set[] = '`' . $key . '` IN (\'' . implode('\', \'', $val) . '\')';
                            break;
                        case 'integer':
                        case 'double':
                            $set[] = '`' . $key . '` ' . $opt . ' ' .  $val;
                            break;
                        default:
                            $set[] = '`' . $key . '`' . $opt . '\'' . addcslashes($val, '\'') . '\'';
                            break;       
                    }
                }
                $where = implode(' AND ', $set);
                break;
            default:
                throw new Exception('unsupport type "' . gettype($offset) . '"', 10);
                break;
        }
        
        return $where;
    }
    
    //将数值转化`key`=value形式
    private function build_query($data){
        $list = array();
        $param = array();
        foreach($data as $key=>$val){
            $opt = '=';
            $tmp = explode(':', $key, 2);
            $key = $tmp[0];
            if (!isset($this->table_fileds[$key])){
                continue;
            }
            if (isset($tmp[1])){
                if (in_array($tmp[1], array('+', '-'))){
                    $opt = $tmp[1];
                }
            }
            $param[$key] = $val;
            if ($opt == '='){
                $list[] = '`' . $key . '` ' . $opt. ' ' . $this->table_fileds[$key];
            }else{
                $list[] = '`' . $key . '` = `' . $key . '` ' . $opt . ' ' . $this->table_fileds[$key];
            }
        }
        return $list ? array($list, $param)  : array();
    }
}
?>