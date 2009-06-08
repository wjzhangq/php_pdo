1. 与php pdo 一样的初始化。例如$db = new db('mysql:host=localhost;dbname=crm;charset=utf8', 'root', 'root');
2. count($db) 获取当前数据库中有多少表
3. 增加getOne, getRow, getCol, getAll 四个直接通过sql获取数据的方法
4. 修改query()函数， 支持 $db->query('select :name from table', array('name'=>'id')) 这种写法。
5. $db['table'][] = array('kk'=>'fff'); //将数据插入到table表
6. $db['table']->insert(array('kk'=>'fff'));  //将数据插入到table表
7. $db['table'][1] = array('kk'=>'fff'); //更新数据
8. unset($db['table][1]); //删除数据