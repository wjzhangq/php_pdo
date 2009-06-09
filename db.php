<?php
/**
 * pdo 的扩展类
 * author: wjzhangq <wjzhangq@126.com>
 */
 
class db extends PDO implements ArrayAccess, Countable
{
    protected $tables; //db中所有表
    
    //初始化
    function __construct($dsn, $username=null, $password=null, $driver_options=null)
    {
        parent::__construct($dsn, $username, $password, $driver_options);
        
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
        //nothing
        //todo: drop a table
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
            throw new Exception('less param for query');
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
                    throw  new Exception ('Unsuport param for query!');
                }
                break;
            case 2:
                $sql = $args[0];
                $param = $args[1];
                break;
            default:
                throw  new Exception ('Unsuport param for query!');
                break;
        }

        if ($param)
        {
            $sth = $this->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            if (!$sth->execute($param))
            {
                throw new Exception("Error sql prepare:$sql");
            }
        }
        else
        {
            if (!$sth = parent::query($sql))
            {
                throw new Exception("Error sql query:$sql");
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
    
    
    //implements
    function offsetExists($offset)
    {
        $where = $this->offset_parse($offset);
        
        $sql = 'SELECT COUNT(*) FROM `' .$this->table_name. '` WHERE ' . $where;
        
        return (bool) $this->db->getOne($sql);
        
    }
    
    function offsetGet($offset)
    {
        $where = $this->offset_parse($offset);
        
        $sql = 'SELECT * FROM `'. $this->table_name .'` WHERE ' . $where;
        
        return $this->db->getRow($sql);
    }
    
    function offsetSet($offset, $value)
    {
        $tmp = array_intersect_key($this->table_fileds, $value);
        if ($tmp)
        {
            if (isset($offset))
            {
                $where = $this->offset_parse($offset);
                $sql = 'UPDATE `'. $this->table_name . '` SET ' . rawurldecode(http_build_query($tmp, '', ',')) . ' WHERE ' . $where;
                
            }
            else
            {
                $sql = 'INSERT INTO `'.$this->table_name.'` SET ' . rawurldecode(http_build_query($tmp, '', ','));
            }
            $param = array_intersect_key($value, $tmp);
            $this->db->query($sql, $param);
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
        return 10;
    }
    
    //私有函数
    private function offset_parse($offset)
    {
        $where = '';
        switch (gettype($offset))
        {
            case 'integer':
            case 'string':
                if ($this->table_key)
                {
                    $where = '`' . $this->table_key . '`= \'' . addcslashes($offset, '\'') . '\'';
                }
                break;
            case 'array':
                $set = array();
                foreach ($offset as $key => $val)
                {
                    if (is_array($val))
                    {
                        $set[] = '`' . $key . '` IN (\'' . implode('\', \'', $val) . '\')';
                    }
                    else
                    {
                        $set[] = '`' . $key . '`=\'' . addcslashes($val, '\'') . '\'';
                    }
                }
                $where = implode(' AND ', $set);
                break;
            default:
        }
        
        return $where;
    }
}
?>