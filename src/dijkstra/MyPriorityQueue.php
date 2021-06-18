<?php

class MyPriorityQueue extends SplPriorityQueue
{
    public function compare($priority1, $priority2)
    {
        if ($priority1[1] === $priority2[1]) return 0;
        return $priority1[1] < $priority2[1] ? -1 : 1;
    }
}

?>