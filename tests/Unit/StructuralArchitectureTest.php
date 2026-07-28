<?php

arch('application code excludes temporary debugging helpers')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray']);
