<template>
  <div class="htp-overlay" @click.self="$emit('close')">
    <div class="htp-modal">
      <button class="htp-close" @click="$emit('close')">&times;</button>
      <h2 class="htp-title">How to Play</h2>

      <!-- Tab Navigation -->
      <div class="htp-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="htp-tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >{{ tab.label }}</button>
      </div>

      <!-- CLASSIC TAB -->
      <div v-if="activeTab === 'classic'" class="htp-body">
        <div class="htp-section">
          <h3>The Premise</h3>
          <p>
            You are the trusted advisors to the ruler of a great civilization.
            Each month, crises and opportunities arise. You must decide which to pursue
            and which to let pass &mdash; but everything has consequences.
          </p>
        </div>

        <div class="htp-section">
          <h3>Each Month</h3>
          <ol>
            <li><strong>Draw Cards</strong> &mdash; Each advisor draws 2 decision cards.</li>
            <li>
              <strong>Assign Roles</strong> &mdash; Choose one card as
              <span class="hl-pos">Positive</span> (you'll roll for it) and one as
              <span class="hl-neg">Negative</span> (its effects happen automatically).
            </li>
            <li>
              <strong>Roll Together</strong> &mdash; All advisors pool their dice against
              the combined difficulty of the Positive cards. Beat the number and the good
              effects apply!
            </li>
            <li>
              <strong>Face the Consequences</strong> &mdash; The Negative cards' effects always
              happen, win or lose. Choose your negatives wisely.
            </li>
          </ol>
        </div>

        <div class="htp-section">
          <h3>Dice &amp; WILD</h3>
          <p>
            Each advisor rolls <strong>3 dice</strong> with numbered faces.
            Rolling <span class="hl-wild">WILD</span> adds a special value
            <em>and</em> triggers a unique character ability &mdash; rallying troops,
            divine insight, or a risky gamble.
          </p>
          <p>
            Each character also has a <strong>manual ability</strong> that can be
            activated once per game for a powerful one-time effect.
          </p>
        </div>

        <div class="htp-section">
          <h3>Items</h3>
          <p>
            Items are earned through card effects and events. They provide
            <strong>passive bonuses</strong> like extra roll power or reduced difficulty.
          </p>
          <ul>
            <li>You can hold up to <strong>2 items</strong> at a time. If you gain a third, you must discard one.</li>
            <li><span class="hl-neg">Cursed items</span> cannot be discarded and impose penalties each round.</li>
            <li>Some cards grant the <em>Remove Curse</em> effect to cleanse cursed items.</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Events</h3>
          <p>
            Every few months, a <strong>kingdom event</strong> triggers &mdash; a plague,
            a festival, a drought. Events modify stats and may impose special mechanics:
          </p>
          <ul>
            <li><strong>Reduce Dice</strong> &mdash; Advisors temporarily roll fewer dice.</li>
            <li><strong>Grant Items</strong> &mdash; Each advisor receives a random item.</li>
            <li><strong>Altered Deal</strong> &mdash; The number of positive and negative cards changes.</li>
            <li><strong>Score Events</strong> &mdash; Modify your renown each month (bonus or penalty).</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Win &amp; Lose</h3>
          <p>
            You have <strong>6 stats</strong> representing the health of your civilization:
            Wealth, Influence, Security, Religion, Food, and Happiness.
          </p>
          <p>
            <span class="hl-neg">If any stat hits 0, the kingdom falls.</span>
            Survive all months and you win! Your final score determines your legacy.
          </p>
        </div>

        <div class="htp-section">
          <h3>Play Modes</h3>
          <ul>
            <li><strong>Solo</strong> &mdash; Play alone with 1 advisor.</li>
            <li><strong>Local (Pass &amp; Play)</strong> &mdash; Multiple advisors on one device, taking turns.</li>
            <li><strong>Online</strong> &mdash; Play with friends in real-time. Invite via friend list or game code.</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Strategy Tips</h3>
          <ul>
            <li>Low difficulty cards are easy to pass but give small rewards.</li>
            <li>High difficulty cards are risky but offer big payoffs.</li>
            <li>Pick negative cards with effects you can afford to absorb.</li>
            <li>Coordinate with other advisors &mdash; you roll together!</li>
            <li>Items with roll bonuses stack &mdash; equip wisely before tough months.</li>
            <li>Keep your stats balanced &mdash; the Balance Bonus rewards even kingdoms.</li>
          </ul>
        </div>
      </div>

      <!-- DUEL TAB -->
      <div v-if="activeTab === 'duel'" class="htp-body">
        <div class="htp-section">
          <h3>Duel Overview</h3>
          <p>
            In <span class="hl-wild">Duel</span> mode, two rival kingdoms compete head-to-head.
            Each player has their own kingdom stats, all starting at 8.
            Outsmart your opponent by choosing the right cards to keep and send.
          </p>
        </div>

        <div class="htp-section">
          <h3>Card Drafting</h3>
          <ol>
            <li><strong>Offer Phase</strong> &mdash; The offerer draws 2 cards and reveals one face-up. The other stays hidden.</li>
            <li><strong>Choose Phase</strong> &mdash; The chooser picks either the revealed card or the hidden card. The offerer gets the other.</li>
            <li><strong>Roll Phase</strong> &mdash; Each player rolls independently against both their cards (kept + received).</li>
            <li><strong>Resolve Phase</strong> &mdash; Negative effects <em>always</em> apply to both players. Positive effects only apply on a successful roll.</li>
          </ol>
        </div>

        <div class="htp-section">
          <h3>Dice &amp; WILD</h3>
          <p>
            Each player rolls <strong>3 dice</strong>. Rolling <span class="hl-wild">WILD</span>
            adds a special value and triggers your character's ability, just like Classic mode.
            Manual abilities can also be used once per game.
          </p>
        </div>

        <div class="htp-section">
          <h3>Items &amp; Events</h3>
          <p>
            Items and events work the same as Classic mode. Some items and events are
            exclusive to one mode &mdash; score-based items only appear in Classic games.
          </p>
        </div>

        <div class="htp-section">
          <h3>Win &amp; Lose</h3>
          <ul>
            <li><span class="hl-pos">Win</span> by reaching <strong>20 in 3 stats</strong>.</li>
            <li><span class="hl-neg">Lose</span> if <strong>any stat hits 0</strong>.</li>
            <li>If the game runs out of rounds, the player with the higher total stat score wins.</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Play Modes</h3>
          <ul>
            <li><strong>Solo vs Bot</strong> &mdash; Challenge an AI opponent.</li>
            <li><strong>Local (Pass &amp; Play)</strong> &mdash; Two players on one device, with a handoff screen between turns.</li>
            <li><strong>Online</strong> &mdash; Challenge friends or find opponents in real-time.</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Strategy Tips</h3>
          <ul>
            <li>When offering, reveal the card that benefits you either way.</li>
            <li>When choosing, weigh the risk of the hidden card vs. the known one.</li>
            <li>Target your opponent's weakest stats with negative effects.</li>
            <li>Race to 20 in 3 stats while keeping your lowest stat safe from 0.</li>
            <li>Online duels affect your <strong>ELO rating</strong> &mdash; play to win!</li>
          </ul>
        </div>
      </div>

      <!-- SCORING & ELO TAB -->
      <div v-if="activeTab === 'scoring'" class="htp-body">
        <div class="htp-section">
          <h3>Classic Scoring</h3>
          <p>
            Your final score in Classic mode is calculated from multiple components:
          </p>
          <div class="formula-box">
            <div class="formula">Final Score = (Base &times; Year Multiplier) + Balance Bonus + Renown</div>
          </div>
        </div>

        <div class="htp-section">
          <h3>Base Score</h3>
          <p>
            The sum of your 6 kingdom stats (Wealth, Influence, Security, Religion, Food, Happiness).
            Each stat ranges from 0&ndash;20, so the maximum base is <strong>120</strong>.
          </p>
        </div>

        <div class="htp-section">
          <h3>Year Multiplier</h3>
          <p>Longer campaigns are rewarded with a higher multiplier based on game length:</p>
          <div class="score-table">
            <div class="table-row table-header">
              <span>Months</span><span>Years</span><span>Multiplier</span>
            </div>
            <div class="table-row"><span>12</span><span>1</span><span>1.0&times;</span></div>
            <div class="table-row"><span>24</span><span>2</span><span>1.4&times;</span></div>
            <div class="table-row"><span>36</span><span>3</span><span>1.7&times;</span></div>
            <div class="table-row"><span>48</span><span>4</span><span>1.9&times;</span></div>
            <div class="table-row"><span>60</span><span>5</span><span>2.0&times;</span></div>
          </div>
        </div>

        <div class="htp-section">
          <h3>Balance Bonus</h3>
          <p>
            A well-rounded kingdom is rewarded. The bonus is calculated as:
          </p>
          <div class="formula-box">
            <div class="formula">max(0, 30 &minus; (highest stat &minus; lowest stat) &times; 3)</div>
          </div>
          <p>
            Perfectly balanced stats (all equal) give the full <strong>+30</strong> bonus.
            A spread of 10+ between your highest and lowest stat gives <strong>0</strong>.
          </p>
        </div>

        <div class="htp-section">
          <h3>Renown (Bonus Score)</h3>
          <p>
            Extra points accumulated during the game from special items, card effects, and events.
            Look for items like <span class="hl-wild">Chronicler's Quill</span> (+2/round)
            and cards with bonus score effects.
          </p>
        </div>

        <div class="htp-section">
          <h3>Score Ranks</h3>
          <div class="score-table">
            <div class="table-row table-header">
              <span>Score</span><span>Rank</span>
            </div>
            <div class="table-row"><span>200+</span><span class="rank-legendary">Legendary</span></div>
            <div class="table-row"><span>150&ndash;199</span><span class="rank-excellent">Excellent</span></div>
            <div class="table-row"><span>100&ndash;149</span><span class="rank-good">Good</span></div>
            <div class="table-row"><span>60&ndash;99</span><span class="rank-adequate">Adequate</span></div>
            <div class="table-row"><span>Below 60</span><span class="rank-poor">Poor</span></div>
          </div>
        </div>

        <div class="htp-section">
          <h3>Duel Scoring</h3>
          <p>
            Duel mode uses a different system. There is no composite score &mdash; victory is based on
            reaching <strong>20 in 3 stats</strong> first, or having the highest total stats when rounds expire.
          </p>
        </div>

        <div class="htp-section">
          <h3>ELO Rating</h3>
          <p>
            Online duel games use an <strong>ELO rating</strong> system for competitive ranking.
            All players start at <strong>1200 ELO</strong>.
          </p>
          <ul>
            <li>Only <strong>online duel</strong> games affect ELO &mdash; solo and local games do not.</li>
            <li>Beating a higher-rated opponent gains more ELO; losing to a lower-rated opponent costs more.</li>
            <li>The K-factor is <strong>32</strong>, meaning each game can shift your rating significantly.</li>
            <li>Track your ELO history on the <strong>Leaderboard</strong> page.</li>
          </ul>
        </div>

        <div class="htp-section">
          <h3>Progression</h3>
          <p>
            Earn <strong>XP</strong> from every completed game:
          </p>
          <ul>
            <li><strong>50 XP</strong> base for completing a game.</li>
            <li><strong>+100 XP</strong> bonus for winning (Classic) or <strong>+150 XP</strong> for winning (Duel).</li>
            <li><strong>1.5&times; multiplier</strong> for online games.</li>
            <li>Complete <strong>achievements</strong> for bonus XP and coin rewards.</li>
            <li>Check the <strong>daily challenge</strong> for a special task each day.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HowToPlay',
  emits: ['close'],
  data() {
    return {
      activeTab: 'classic',
      tabs: [
        { key: 'classic', label: 'Classic' },
        { key: 'duel', label: 'Duel' },
        { key: 'scoring', label: 'Scoring & ELO' },
      ],
    };
  },
};
</script>

<style scoped>
.htp-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 20px;
}

.htp-modal {
  background: linear-gradient(180deg, #1a1209, #0d0a06);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 30px 35px;
  max-width: 650px;
  width: 100%;
  max-height: 85vh;
  overflow-y: auto;
  position: relative;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6), 0 0 30px rgba(212, 168, 67, 0.1);
}

.htp-close {
  position: absolute;
  top: 12px;
  right: 16px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.8rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  transition: color 0.2s;
}

.htp-close:hover {
  color: var(--accent-gold);
  background: none;
  box-shadow: none;
  transform: none;
}

.htp-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  text-align: center;
  margin-bottom: 16px;
  letter-spacing: 2px;
}

/* Tab Navigation */
.htp-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.3);
  padding-bottom: 0;
}

.htp-tab {
  flex: 1;
  padding: 8px 12px;
  background: none;
  border: 1px solid transparent;
  border-bottom: none;
  border-radius: 6px 6px 0 0;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  bottom: -1px;
}

.htp-tab:hover {
  color: var(--text-bright);
  background: rgba(212, 168, 67, 0.06);
}

.htp-tab.active {
  color: var(--accent-gold);
  border-color: rgba(138, 106, 46, 0.3);
  background: linear-gradient(180deg, rgba(212, 168, 67, 0.08), transparent);
  border-bottom: 1px solid #0d0a06;
}

.htp-body {
  animation: fadeTab 0.2s ease;
}

@keyframes fadeTab {
  from { opacity: 0; }
  to { opacity: 1; }
}

.htp-section {
  margin-bottom: 20px;
}

.htp-section h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.05rem;
  margin-bottom: 8px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
  padding-bottom: 4px;
}

.htp-section p {
  color: var(--text-primary);
  line-height: 1.6;
  font-size: 1rem;
  margin-bottom: 6px;
}

.htp-section ol,
.htp-section ul {
  color: var(--text-primary);
  line-height: 1.7;
  padding-left: 22px;
  font-size: 1rem;
}

.htp-section li {
  margin-bottom: 6px;
}

.hl-pos { color: #27ae60; font-weight: 600; }
.hl-neg { color: #c0392b; font-weight: 600; }
.hl-wild { color: #d4a843; font-weight: 700; }

/* Formula boxes */
.formula-box {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  padding: 10px 14px;
  margin: 8px 0;
  text-align: center;
}

.formula {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
}

/* Score tables */
.score-table {
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  overflow: hidden;
  margin: 8px 0;
}

.table-row {
  display: flex;
  padding: 6px 14px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.table-row:last-child {
  border-bottom: none;
}

.table-row span {
  flex: 1;
  color: var(--text-primary);
  font-size: 0.9rem;
}

.table-header {
  background: rgba(212, 168, 67, 0.08);
}

.table-header span {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Score rank colors */
.rank-legendary { color: #f0c040; font-weight: 700; }
.rank-excellent { color: #4caf50; font-weight: 600; }
.rank-good { color: #60b8e0; font-weight: 600; }
.rank-adequate { color: var(--text-secondary); }
.rank-poor { color: #e57373; }

@media (max-width: 768px) {
  .htp-modal {
    padding: 20px 16px;
  }

  .htp-tab {
    font-size: 0.75rem;
    padding: 6px 8px;
  }

  .formula {
    font-size: 0.8rem;
  }
}
</style>
