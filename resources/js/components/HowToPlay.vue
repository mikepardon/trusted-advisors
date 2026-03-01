<template>
  <div class="htp-overlay" @click.self="$emit('close')">
    <div class="htp-modal">
      <button class="htp-close" @click="$emit('close')">&times;</button>
      <h2 class="htp-title">How to Play</h2>

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
          Survive all months and you win! The higher your stats at the end,
          the more glorious your legacy.
        </p>
      </div>

      <div class="htp-section">
        <h3>Duel Mode</h3>
        <p>
          In <span class="hl-wild">Duel</span> mode, two rival kingdoms compete head-to-head.
          Each player has their own kingdom stats starting at 8.
        </p>
        <ul>
          <li>One player <strong>offers</strong> cards; the other <strong>chooses</strong> which to take.</li>
          <li>Each player rolls independently. Negative effects <em>always</em> apply; positive effects only on success.</li>
          <li><strong>Win</strong> by reaching 20 in 3 stats. <strong>Lose</strong> if any stat hits 0.</li>
        </ul>
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
        <h3>Progression</h3>
        <p>
          Earn <strong>XP</strong> from every game. Level up to unlock new characters,
          items, and cosmetics. Online duel games also award <strong>ELO rating</strong>
          for competitive ranking.
        </p>
        <ul>
          <li>Complete <strong>achievements</strong> for bonus XP and coin rewards.</li>
          <li>Check the <strong>daily challenge</strong> for a special task each day.</li>
          <li>Climb the <strong>leaderboard</strong> to see how you rank against other players.</li>
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
          <li>In Duel, consider which card your opponent will want before offering.</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HowToPlay',
  emits: ['close'],
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
  margin-bottom: 22px;
  letter-spacing: 2px;
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
</style>
