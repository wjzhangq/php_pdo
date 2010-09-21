常规
1. 与php pdo 一样的初始化。例如$db = new db('mysql:host=localhost;dbname=crm;charset=utf8', 'root', 'root');
2. 增加getOne, getRow, getCol, getAll 四个直接通过sql获取数据的方法
3. 修改query()函数， 支持 $db->query('select :name from table', array('name'=>'id')) 这种写法。
4. $db['table']->insert(array('kk'=>'fff'));  //将数据插入到table表,并返回插入id
魔法
1. count($db) 获取当前数据库中有多少表
2. $db['table']; //获取tableb表对象
3. $db['table'][] = array('kk'=>'fff'); //将array()数据插入到table表
4. $db['table'][1] = array('kk'=>'fff'); //更新主键为1的数据
5. unset($db['table][1]); //删除主键为1的数据
6. $db['table'][array('id'=>1)]; //返回id等于1的数据
9. $db['table'][array('id:>'=>1)]; //返回id大于1的数据, 支持>, <, >=, <=, =
10. $db['table'][array('id'=>array(1, 3, 5))]; //返回id等于1, 3, 5的数据
11. $db['table']->order('id DESC')->limit(0, 1)->query(array('id:>'=>1));
12. count($db['table']->where(array('id:>'=>1))); //id 大于1的数据数量
13. $db['table'][1] = array('kk:+'=>2); //更新主键为1的数据z字段kk自己增加2