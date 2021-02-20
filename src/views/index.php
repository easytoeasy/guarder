<!DOCTYPE html>
<html lang="en">

<head>
  <title>Guarder Status</title>
  <link href="css/supervisor.css" rel="stylesheet" type="text/css">
  <link href="images/icon.png" rel="icon" type="image/png">
</head>


<body>
  <div id="wrapper">

    <div id="header">
      <img alt="Supervisor status" src="images/supervisor.gif">
    </div>

    <div>
      <div class="status_msg"><?= $this->message ?></div>

      <ul class="clr" id="buttons">
        <li class="action-button"><a href="index.html?action=refresh">Refresh</a></li>
        <li class="action-button"><a href="index.html?action=restartall">Restart All</a></li>
        <li class="action-button"><a href="index.html?action=stopall">Stop All</a></li>
        <li class="action-button"><a href="tail.php" target="_blank">logfile</a></li>
      </ul>

      <table cellspacing="0">
        <thead>
          <tr>
            <th class="state">State</th>
            <th class="desc">Description</th>
            <th class="name">Name</th>
            <th class="action">Action</th>
          </tr>
        </thead>

        <tbody>
          <?php

          use pzr\guarder\State;

          if ($this->taskers) foreach ($this->taskers as $c) { ?>
            <tr class="">
              <td class="status"><span class="status<?= State::css($c->state) ?>"><?= State::desc($c->state) ?></span></td>
              <td><span>pid <?= $c->pid ?>, <?= $c->uptime ?></span></td>
              <td><?= $c->program ?></td>
              <td class="action">
                <ul>
                  <?php if (in_array($c->state, State::runingState())) { ?>
                    <li>
                      <a href="index.html?program=<?= $c->program ?>&action=restart" name="Restart">Restart</a>
                    </li>
                    <li>
                      <a href="index.html?program=<?= $c->program ?>&action=stop" name="Stop">Stop</a>
                    </li>
                  <?php } else { ?>
                    <li>
                      <a href="index.html?program=<?= $c->program ?>&action=start" name="Start">Start</a>
                    </li>
                  <?php } ?>
                  <li>
                    <a href="stderr.php?program=<?= $c->program ?>" name="Tail -f Stderr" target="_blank">Tail -f Stderr</a>
                  </li>
                  <li>
                    <a href="index.html?program=<?= $c->program ?>&action=clear" name="Clear Stderr">Clear Stderr</a>
                  </li>
                </ul>
              </td>
            </tr>
          <?php  } ?>
        </tbody>
      </table>

    </div>
</body>

</html>