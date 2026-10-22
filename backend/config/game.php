<?php

return [
    'upgrade_gold_per_level' => 80,
    'upgrade_diamonds_per_level' => 5,
    'chests' => [
        'bronze' => ['id' => 'bronze', 'name' => 'Bronze Stash', 'cost_gold' => 50, 'cost_diamonds' => 0, 'card_code' => 'ember-archer', 'card_name' => 'Ember Archer', 'boost_code' => 'speed-burst', 'boost_name' => 'Speed Burst', 'boost_quantity' => 1],
        'silver' => ['id' => 'silver', 'name' => 'Silver Cache', 'cost_gold' => 120, 'cost_diamonds' => 0, 'card_code' => 'stone-warden', 'card_name' => 'Stone Warden', 'boost_code' => 'fortify', 'boost_name' => 'Fortify', 'boost_quantity' => 2],
        'gold' => ['id' => 'gold', 'name' => 'Gold Legendary Chest', 'cost_gold' => 250, 'cost_diamonds' => 10, 'card_code' => 'vault-mage', 'card_name' => 'Vault Mage', 'boost_code' => 'meteor-strike', 'boost_name' => 'Meteor Strike', 'boost_quantity' => 3],
    ],
    'boosts' => [
        'speed-burst' => ['name' => 'Speed Burst', 'description' => 'Double tower firing speed for 10 seconds.'],
        'fortify' => ['name' => 'Fortify', 'description' => 'Restore 5 lives, up to the initial 20 lives.'],
        'meteor-strike' => ['name' => 'Meteor Strike', 'description' => 'Deal 100 damage to every enemy currently on the map.'],
    ],
];
