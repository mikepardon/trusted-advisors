<template>
    <div class="setup-screen">
        <!-- Auth loading -->
        <div v-if="auth.state.loading" class="auth-loading">
            <p>Loading...</p>
        </div>

        <!-- Not logged in -->
        <LoginRegister v-else-if="!auth.state.user" />

        <!-- Logged in -->
        <template v-else>
            <!-- Mobile Menu Overlay -->
            <div
                v-if="showMobileMenu"
                class="mobile-menu-overlay"
                @click.self="showMobileMenu = false"
            >
                <div class="mobile-menu-panel">
                    <button
                        class="mobile-menu-item"
                        @click="
                            showMobileMenu = false;
                            openRules();
                        "
                    >
                        Rules
                    </button>
                    <button
                        class="mobile-menu-item"
                        @click="
                            showMobileMenu = false;
                            openTutorial();
                        "
                    >
                        Tutorial
                    </button>
                    <router-link
                        to="/season-pass"
                        class="mobile-menu-item"
                        @click="showMobileMenu = false"
                        >Season Pass</router-link
                    >
                    <router-link
                        to="/league"
                        class="mobile-menu-item"
                        @click="showMobileMenu = false"
                        >League</router-link
                    >
                    <router-link
                        to="/settings"
                        class="mobile-menu-item"
                        @click="showMobileMenu = false"
                        >Settings</router-link
                    >
                    <router-link
                        v-if="auth.state.user?.is_admin"
                        to="/admin"
                        class="mobile-menu-item"
                        @click="showMobileMenu = false"
                        >Admin</router-link
                    >
                </div>
            </div>

            <!-- STEP 0: Mode selection — the war table hub -->
            <Transition name="fade" mode="out-in">
                <div v-if="step === 'mode'" key="mode" class="home-hub">
                    <!-- Top banners (rotating events / announcements / challenges) sit above the table -->
                    <div class="hub-banners">
                        <RotatingEventBanner />
                        <AnnouncementsBanner />
                        <div class="daily-enhanced">
                            <DailyChallengeBanner />
                            <WeeklyChallengeBanner />
                        </div>
                    </div>

                    <!-- The war table: a circular cover-art disc with the PLAY crest at its heart
           and four seat medallions orbiting it. -->
                    <div class="war-table">
                        <div class="war-table-disc" :style="discStyle"></div>

                        <!-- Central PLAY crest → the online-duel entry (primary action) -->
                        <button
                            class="table-crest hub-btn"
                            title="Play online duel"
                            @click="
                                playSound('clickCard');
                                gameMode = 'online';
                                selectMode();
                            "
                        >
                            <span class="crest-glyph">&#9876;</span>
                            <span class="crest-title">PLAY</span>
                            <span class="crest-sub">ONLINE DUEL</span>
                        </button>

                        <!-- Seat: Pass (top-left) -->
                        <button
                            class="table-seat seat-tl hub-btn"
                            title="Season Pass"
                            @click="
                                playSound('clickNav');
                                $router.push('/season-pass');
                            "
                        >
                            <span class="seat-medallion">
                                <span class="seat-glyph">&#10022;</span>
                            </span>
                            <span class="seat-label-box">
                                <span class="seat-label">PASS</span>
                                <span class="seat-meta"
                                    >Tier
                                    {{
                                        auth.state.user?.season_pass_tier ?? 0
                                    }}</span
                                >
                            </span>
                        </button>

                        <!-- Seat: League (top-right) -->
                        <button
                            class="table-seat seat-tr hub-btn"
                            title="League"
                            @click="
                                playSound('clickNav');
                                $router.push('/league');
                            "
                        >
                            <span class="seat-medallion">
                                <span class="seat-glyph">&#9960;</span>
                            </span>
                            <span class="seat-label-box">
                                <span class="seat-label">LEAGUE</span>
                                <span class="seat-meta"
                                    >{{
                                        auth.state.user?.league_points ?? 0
                                    }}
                                    pts</span
                                >
                            </span>
                        </button>

                        <!-- Seat: Shop (bottom-left) -->
                        <button
                            class="table-seat seat-bl hub-btn"
                            title="Shop"
                            @click="
                                playSound('clickNav');
                                $router.push('/shop');
                            "
                        >
                            <span class="seat-medallion">
                                <span class="seat-glyph">&#9673;</span>
                                <span
                                    v-if="freeChest.state.available"
                                    class="ta-dot seat-dot"
                                ></span>
                            </span>
                            <span class="seat-label-box">
                                <span class="seat-label">SHOP</span>
                                <span class="seat-meta">{{
                                    freeChest.state.available
                                        ? "Chest ready!"
                                        : "Gold & wares"
                                }}</span>
                            </span>
                        </button>

                        <!-- Seat: Allies (bottom-right) -->
                        <button
                            class="table-seat seat-br hub-btn"
                            title="Allies"
                            @click="
                                playSound('clickNav');
                                $router.push('/friends');
                            "
                        >
                            <span class="seat-medallion">
                                <span class="seat-glyph">&#9786;</span>
                                <span
                                    v-if="pendingInvites.length > 0"
                                    class="ta-dot seat-dot"
                                ></span>
                            </span>
                            <span class="seat-label-box">
                                <span class="seat-label">ALLIES</span>
                                <span class="seat-meta">{{
                                    pendingInvites.length > 0
                                        ? pendingInvites.length + " waiting"
                                        : "Duel a friend"
                                }}</span>
                            </span>
                        </button>
                    </div>

                    <!-- The Ledger: resume / streak / achievements / solo-campaign drawer -->
                    <div class="hub-ledger">
                        <div class="ta-divider">
                            <span class="ta-divider-label">THE LEDGER</span>
                            <span class="ta-divider-line"></span>
                        </div>

                        <!-- Resume / previous games -->
                        <div
                            class="ta-row ledger-row"
                            @click="
                                playSound('clickNav');
                                $router.push('/campaigns');
                            "
                        >
                            <div class="ledger-art"></div>
                            <div class="ta-row-body">
                                <div class="ta-row-title">
                                    {{
                                        homeStats.activeGames > 0
                                            ? "Continue Campaign"
                                            : "Previous Games"
                                    }}
                                </div>
                                <div class="ta-row-meta">
                                    {{
                                        homeStats.activeGames > 0
                                            ? homeStats.activeGames +
                                              " game" +
                                              (homeStats.activeGames === 1
                                                  ? ""
                                                  : "s") +
                                              " in progress"
                                            : "Review completed reigns"
                                    }}
                                </div>
                            </div>
                            <span class="ta-pill ta-pill--blue">{{
                                homeStats.activeGames > 0 ? "RESUME" : "VIEW"
                            }}</span>
                        </div>

                        <!-- Daily streak / tribute (HomeHero handles claim state itself) -->
                        <HomeHero />

                        <!-- Achievements -->
                        <div
                            class="ta-row ledger-row"
                            :class="{
                                'ta-row--ready': homeStats.unclaimed > 0,
                            }"
                            @click="
                                playSound('clickNav');
                                $router.push('/achievements');
                            "
                        >
                            <div
                                class="ta-medallion ledger-medallion"
                                :class="{
                                    'ta-medallion--locked':
                                        homeStats.unclaimed === 0,
                                }"
                            >
                                <div
                                    class="ta-medallion-face"
                                    :class="{
                                        'ta-medallion-face--locked':
                                            homeStats.unclaimed === 0,
                                    }"
                                >
                                    &#10022;
                                    <span
                                        v-if="homeStats.unclaimed > 0"
                                        class="ta-dot medallion-dot"
                                    ></span>
                                </div>
                            </div>
                            <div class="ta-row-body">
                                <div class="ta-row-title">Achievements</div>
                                <div class="ta-row-meta">
                                    {{
                                        homeStats.unclaimed > 0
                                            ? homeStats.unclaimed +
                                              " medal" +
                                              (homeStats.unclaimed === 1
                                                  ? ""
                                                  : "s") +
                                              " ready to claim"
                                            : "Medals, titles and renown"
                                    }}
                                </div>
                            </div>
                            <span
                                class="ta-pill"
                                :class="
                                    homeStats.unclaimed > 0
                                        ? 'ta-pill--claim'
                                        : 'ta-pill--locked'
                                "
                                >{{
                                    homeStats.unclaimed > 0
                                        ? "CLAIM " + homeStats.unclaimed
                                        : "VIEW"
                                }}</span
                            >
                        </div>

                        <!-- Solo campaign CTA → single-player start path -->
                        <button
                            class="ta-cta ta-cta--dark solo-cta"
                            @click="
                                playSound('clickCard');
                                gameMode = 'single';
                                selectMode();
                            "
                        >
                            &#9819; SOLO CAMPAIGN
                        </button>

                        <!-- Tournaments (kept, only when enabled) -->
                        <button
                            v-if="auth.state.user?.tournaments_enabled"
                            class="ta-cta ta-cta--dark solo-cta"
                            @click="
                                playSound('clickCard');
                                $router.push('/tournaments');
                            "
                        >
                            &#9878; TOURNAMENTS
                        </button>

                        <!-- Pending Game Invites -->
                        <div
                            v-if="pendingInvites.length > 0"
                            class="invites-panel"
                        >
                            <div class="ta-divider">
                                <span class="ta-divider-label"
                                    >GAME INVITES</span
                                >
                                <span class="ta-divider-line"></span>
                            </div>
                            <div class="invite-list">
                                <div
                                    v-for="invite in pendingInvites"
                                    :key="invite.id"
                                    class="invite-row"
                                >
                                    <span class="invite-from"
                                        >{{ invite.sender?.name }} invites you
                                        to a game</span
                                    >
                                    <div class="invite-actions">
                                        <button
                                            class="btn-primary btn-sm"
                                            @click="acceptInvite(invite)"
                                        >
                                            Join
                                        </button>
                                        <button
                                            class="btn-sm btn-decline"
                                            @click="declineInvite(invite)"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP: Game type selection (cooperative vs duel) -->
                <div v-else-if="step === 'gameType'" key="gameType">
                    <div class="card-panel">
                        <h2 class="section-title">Choose Your Challenge</h2>
                        <p class="flavor-text">
                            Will you work together to save the realm, or compete
                            to build the greater kingdom?
                        </p>

                        <div class="mode-cards">
                            <div
                                class="mode-card"
                                @click="
                                    playSound('clickCard');
                                    gameType = 'cooperative';
                                    step = 'settings';
                                "
                            >
                                <h3 class="mode-title">
                                    {{
                                        gameMode === "single"
                                            ? "Classic"
                                            : "Cooperative"
                                    }}
                                </h3>
                                <p class="mode-desc">
                                    {{
                                        gameMode === "single"
                                            ? "You've been requested to save the land, are you up for the challenge?"
                                            : "Work together to guide the kingdom through crisis"
                                    }}
                                </p>
                            </div>
                            <div
                                class="mode-card"
                                @click="
                                    playSound('clickCard');
                                    gameType = 'duel';
                                    numberPlayers = 2;
                                    totalRounds = 24;
                                    step = 'settings';
                                "
                            >
                                <h3 class="mode-title">Duel</h3>
                                <p class="mode-desc">
                                    {{
                                        gameMode === "single"
                                            ? "Challenge a bot: draft cards, build rival kingdoms"
                                            : "Compete head-to-head: draft cards, build rival kingdoms (2 players)"
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button
                                class="back-btn"
                                @click="
                                    playSound('clickNav');
                                    step = 'mode';
                                "
                            >
                                &#8592; Back
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STEP 1: Game settings (players/friends + game length) -->
                <div v-else-if="step === 'settings'" key="settings">
                    <div class="card-panel">
                        <!-- Pass and Play: player count (hidden for duel, locked to 2) -->
                        <template
                            v-if="
                                gameMode === 'pass_and_play' &&
                                gameType !== 'duel'
                            "
                        >
                            <h2 class="section-title">How Many Advisors?</h2>
                            <p class="flavor-text">
                                The realm needs leaders. How many advisors will
                                answer the call?
                            </p>

                            <div class="player-select">
                                <label>Number of Advisors:</label>
                                <div class="player-buttons">
                                    <button
                                        v-for="n in 5"
                                        :key="n + 1"
                                        :class="{
                                            'btn-primary':
                                                numberPlayers === n + 1,
                                        }"
                                        @click="
                                            playSound('clickToggle');
                                            numberPlayers = n + 1;
                                        "
                                    >
                                        {{ n + 1 }}
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Duel mode info (hide all options for event games) -->
                        <template
                            v-if="gameType === 'duel' && !rotatingEventId"
                        >
                            <h2 class="section-title">Duel Mode</h2>
                            <p class="flavor-text">
                                {{
                                    gameMode === "single"
                                        ? "Challenge a bot to build rival kingdoms. Pick a card to keep and send the other to your rival."
                                        : "Two advisors compete to build rival kingdoms. Pick a card to keep and send the other to your rival. 2 players locked."
                                }}
                            </p>

                            <!-- Bot difficulty selector for single-player duel -->
                            <div
                                v-if="gameMode === 'single'"
                                class="bot-difficulty-select"
                            >
                                <div class="player-buttons">
                                    <button
                                        v-for="d in ['easy', 'medium', 'hard']"
                                        :key="d"
                                        :class="{
                                            'btn-primary': botDifficulty === d,
                                        }"
                                        @click="
                                            playSound('clickToggle');
                                            botDifficulty = d;
                                            gatherAdvisors();
                                        "
                                    >
                                        {{
                                            d.charAt(0).toUpperCase() +
                                            d.slice(1)
                                        }}
                                    </button>
                                </div>
                            </div>

                        </template>

                        <!-- Online: friends picker (cooperative only) -->
                        <template
                            v-if="gameMode === 'online' && gameType !== 'duel'"
                        >
                            <h2 class="section-title">Invite Your Allies</h2>
                            <p class="flavor-text">
                                Select the friends who will join your council.
                                You will be added automatically.
                            </p>

                            <div v-if="friendsLoading" class="friends-loading">
                                Loading friends...
                            </div>

                            <div v-else class="friends-picker">
                                <!-- Add friend inline -->
                                <div class="add-friend-inline">
                                    <input
                                        v-model="addFriendUsername"
                                        type="text"
                                        placeholder="Add friend by username..."
                                        class="friend-input"
                                        @keyup.enter="addFriendInline"
                                    />
                                    <button
                                        class="btn-primary btn-sm"
                                        :disabled="!addFriendUsername.trim()"
                                        @click="addFriendInline"
                                    >
                                        Add
                                    </button>
                                </div>
                                <p v-if="addFriendError" class="friend-error">
                                    {{ addFriendError }}
                                </p>
                                <p
                                    v-if="addFriendSuccess"
                                    class="friend-success"
                                >
                                    {{ addFriendSuccess }}
                                </p>

                                <!-- Pending received friend requests -->
                                <div
                                    v-if="pendingReceivedFriends.length > 0"
                                    class="received-requests"
                                >
                                    <label class="received-label"
                                        >Pending Friend Requests</label
                                    >
                                    <div
                                        v-for="req in pendingReceivedFriends"
                                        :key="req.id"
                                        class="received-row"
                                    >
                                        <span class="received-name">{{
                                            req.user.name
                                        }}</span>
                                        <button
                                            class="btn-primary btn-sm"
                                            @click.stop="
                                                acceptFriendInline(req.id)
                                            "
                                        >
                                            Accept
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="availableFriends.length === 0"
                                    class="no-friends"
                                >
                                    <p>
                                        No friends yet. Add a friend above to
                                        get started!
                                    </p>
                                </div>

                                <div v-else class="friend-pick-list">
                                    <div
                                        v-for="friend in availableFriends"
                                        :key="friend.id"
                                        :class="[
                                            'friend-pick-row',
                                            {
                                                'friend-selected':
                                                    selectedFriendIds.includes(
                                                        friend.user.id,
                                                    ),
                                            },
                                        ]"
                                        @click="toggleFriend(friend.user.id)"
                                    >
                                        <span class="friend-pick-check">{{
                                            selectedFriendIds.includes(
                                                friend.user.id,
                                            )
                                                ? "&#10003;"
                                                : ""
                                        }}</span>
                                        <span class="friend-pick-name">{{
                                            friend.user.name
                                        }}</span>
                                    </div>
                                </div>

                                <div class="selected-count">
                                    {{ selectedFriendIds.length + 1 }} advisor{{
                                        selectedFriendIds.length + 1 !== 1
                                            ? "s"
                                            : ""
                                    }}
                                    (you +
                                    {{ selectedFriendIds.length }} friend{{
                                        selectedFriendIds.length !== 1
                                            ? "s"
                                            : ""
                                    }})
                                </div>
                            </div>
                        </template>

                        <!-- Single player heading (hide for event games) -->
                        <template
                            v-if="
                                gameMode === 'single' &&
                                gameType !== 'duel' &&
                                !rotatingEventId
                            "
                        >
                            <h2 class="section-title">
                                The King's 5-Year Plan
                            </h2>
                            <p class="flavor-text">
                                You have been appointed by the King to guide the
                                realm through a 5-year plan. Survive — that is
                                all that matters.
                            </p>
                        </template>

                        <!-- Custom Game (premium only, hidden for event games) -->
                        <div
                            v-if="
                                auth.state.user?.is_premium && !rotatingEventId
                            "
                            class="custom-game-section"
                        >
                            <label class="custom-toggle">
                                <input
                                    v-model="isCustomGame"
                                    type="checkbox"
                                    @change="onCustomToggle"
                                />
                                <span class="custom-toggle-label"
                                    >Custom Game</span
                                >
                            </label>

                            <p v-if="isCustomGame" class="custom-warning">
                                Custom games do not count towards leaderboards,
                                achievements, or XP.
                            </p>

                            <div v-if="isCustomGame" class="custom-options">
                                <div class="custom-option">
                                    <label
                                        >Starting Stats:
                                        {{ customStartingStats }}</label
                                    >
                                    <input
                                        v-model.number="customStartingStats"
                                        type="range"
                                        min="1"
                                        max="20"
                                        class="custom-slider"
                                    />
                                </div>

                                <div class="custom-option">
                                    <label class="hr-label">House Rules</label>
                                    <label class="hr-toggle"
                                        ><input
                                            v-model="
                                                houseRules.no_negative_effects
                                            "
                                            type="checkbox"
                                        />
                                        No Negative Effects</label
                                    >
                                    <label class="hr-toggle"
                                        ><input
                                            v-model="
                                                houseRules.double_positive_effects
                                            "
                                            type="checkbox"
                                        />
                                        Double Positive Effects</label
                                    >
                                    <label class="hr-toggle"
                                        ><input
                                            v-model="
                                                houseRules.random_starting_stats
                                            "
                                            type="checkbox"
                                        />
                                        Random Starting Stats</label
                                    >
                                    <label class="hr-toggle"
                                        ><input
                                            v-model="houseRules.hardcore_mode"
                                            type="checkbox"
                                        />
                                        Hardcore (lose at stat &le; 3)</label
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Private lobby (premium only, online) -->
                        <div
                            v-if="
                                gameMode === 'online' &&
                                auth.state.user?.is_premium
                            "
                            class="private-section"
                        >
                            <label class="custom-toggle">
                                <input
                                    v-model="isPrivateGame"
                                    type="checkbox"
                                />
                                <span class="custom-toggle-label"
                                    >Private Game</span
                                >
                            </label>
                            <input
                                v-if="isPrivateGame"
                                v-model="lobbyPassword"
                                type="text"
                                class="lobby-password-input"
                                placeholder="Set password..."
                            />
                        </div>

                        <div class="step-nav">
                            <button
                                class="back-btn"
                                @click="
                                    playSound('clickNav');
                                    goBack();
                                "
                            >
                                &#8592; Back
                            </button>
                            <button
                                v-if="
                                    !(
                                        gameMode === 'single' &&
                                        gameType === 'duel'
                                    )
                                "
                                class="btn-primary start-btn"
                                :disabled="
                                    loading ||
                                    (gameMode === 'online' &&
                                        gameType !== 'duel' &&
                                        selectedFriendIds.length === 0) ||
                                    (isPrivateGame && !lobbyPassword.trim())
                                "
                                @click="
                                    playSound('clickButton');
                                    gatherAdvisors();
                                "
                            >
                                {{
                                    loading
                                        ? "Creating..."
                                        : gameMode === "online" &&
                                            gameType === "duel"
                                          ? "Find Opponent"
                                          : "Gather Advisors"
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STEP: Matchmaking queue (online duel) -->
                <div v-else-if="step === 'matchmaking'" key="matchmaking">
                    <MatchmakingQueue
                        :total-rounds="totalRounds"
                        @matched="onMatchFound"
                        @cancelled="step = 'mode'"
                    />
                </div>

                <!-- STEP 2: Story intro -->
                <div
                    v-else-if="step === 'story'"
                    key="story"
                    class="story-step"
                >
                    <StoryIntro
                        :number-players="numberPlayers"
                        @continue="step = 'characters'"
                    />
                </div>

                <!-- STEP 3: Character selection carousel -->
                <div
                    v-else-if="step === 'characters'"
                    key="characters"
                    class="ta-page advisors-page"
                >
                    <div class="ta-drawer">
                        <div class="ta-page-header">
                            <button
                                class="ta-back"
                                aria-label="Back"
                                @click="
                                    playSound('clickNav');
                                    allPlayersPicked
                                        ? undoLastPick()
                                        : goBack();
                                "
                            >
                                &lsaquo;
                            </button>
                            <div class="ta-page-heading">
                                <div class="ta-page-title">
                                    {{
                                        allPlayersPicked
                                            ? "Your Council"
                                            : "Choose Your Advisor"
                                    }}
                                </div>
                                <div class="ta-page-sub">
                                    {{
                                        allPlayersPicked
                                            ? "Ready to begin the reign"
                                            : "One advisor per campaign — their dice are your hand"
                                    }}
                                </div>
                            </div>
                            <span
                                v-if="
                                    !(
                                        gameMode === 'single' &&
                                        gameType === 'duel'
                                    )
                                "
                                class="ta-stat-pill"
                                >PLAYER {{ currentPickingPlayer }}</span
                            >
                        </div>

                        <div class="ta-page-body">
                            <!-- All players have picked: show summary -->
                            <div v-if="allPlayersPicked" class="summary-panel">
                                <div class="summary-picks">
                                    <div
                                        v-for="(
                                            charId, playerNum
                                        ) in playerSelections"
                                        :key="playerNum"
                                        class="ta-row summary-pick"
                                    >
                                        <div class="summary-portrait-wrap">
                                            <img
                                                :src="getCharacterImage(charId)"
                                                alt="Advisor"
                                                class="summary-portrait"
                                            />
                                        </div>
                                        <div class="ta-row-body">
                                            <div
                                                class="ta-row-meta summary-player"
                                            >
                                                Player {{ playerNum }}
                                            </div>
                                            <div
                                                class="ta-row-title summary-name"
                                            >
                                                {{ getCharacterName(charId) }}
                                            </div>
                                            <div
                                                v-if="
                                                    getCharacterBonusLabel(
                                                        charId,
                                                    )
                                                "
                                                class="summary-bonus"
                                            >
                                                {{
                                                    getCharacterBonusLabel(
                                                        charId,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    class="ta-cta begin-cta"
                                    :disabled="starting"
                                    @click="
                                        playSound('clickButton');
                                        startGame();
                                    "
                                >
                                    <span v-if="!starting">&#9819;</span>
                                    {{
                                        starting
                                            ? "BEGINNING..."
                                            : "BEGIN THE REIGN"
                                    }}
                                </button>
                            </div>

                            <!-- Picking in progress -->
                            <div v-else class="picking-screen">
                                <div class="carousel-wrapper">
                                    <Swiper
                                        :modules="swiperModules"
                                        effect="cards"
                                        :grab-cursor="true"
                                        :cards-effect="{
                                            perSlideOffset: 8,
                                            perSlideRotate: 2,
                                            rotate: true,
                                            slideShadows: false,
                                        }"
                                        :style="{ overflow: 'visible' }"
                                        @swiper="onSwiper"
                                        @slide-change="onSlideChange"
                                    >
                                        <SwiperSlide
                                            v-for="char in availableCharacters"
                                            :key="char.id"
                                        >
                                            <div class="advisor-card">
                                                <!-- Header: portrait + name + role + blurb -->
                                                <div class="advisor-head">
                                                    <div
                                                        class="advisor-portrait-wrap"
                                                    >
                                                        <img
                                                            :src="
                                                                char.image_url ||
                                                                '/images/character.png'
                                                            "
                                                            :alt="char.name"
                                                            class="advisor-portrait"
                                                        />
                                                        <span
                                                            v-if="
                                                                char.level > 0
                                                            "
                                                            class="advisor-level-pip"
                                                            >{{
                                                                char.level
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="advisor-head-text"
                                                    >
                                                        <h3
                                                            class="advisor-name"
                                                        >
                                                            {{
                                                                char.display_name ||
                                                                char.name
                                                            }}
                                                        </h3>
                                                        <div
                                                            class="advisor-role"
                                                        >
                                                            COURT ADVISOR
                                                            &middot;
                                                            {{
                                                                (
                                                                    char.wild_ability ||
                                                                    ""
                                                                ).toUpperCase()
                                                            }}
                                                        </div>
                                                        <p class="advisor-desc">
                                                            {{
                                                                char.description
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        getCharacterBonusLabel(
                                                            char.id,
                                                        )
                                                    "
                                                    class="advisor-bonus-badge"
                                                >
                                                    {{
                                                        getCharacterBonusLabel(
                                                            char.id,
                                                        )
                                                    }}
                                                </div>
                                                <!-- Upgrade bonuses -->
                                                <div
                                                    v-if="
                                                        hasUpgradeBonuses(char)
                                                    "
                                                    class="advisor-upgrades"
                                                >
                                                    <span
                                                        v-if="
                                                            char.extra_item_slots >
                                                            0
                                                        "
                                                        class="advisor-upgrade-tag"
                                                        >+{{
                                                            char.extra_item_slots
                                                        }}
                                                        Item Slot{{
                                                            char.extra_item_slots >
                                                            1
                                                                ? "s"
                                                                : ""
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="
                                                            char.card_redraws >
                                                            0
                                                        "
                                                        class="advisor-upgrade-tag"
                                                        >{{
                                                            char.card_redraws
                                                        }}
                                                        Redraw{{
                                                            char.card_redraws >
                                                            1
                                                                ? "s"
                                                                : ""
                                                        }}</span
                                                    >
                                                    <span
                                                        v-for="(
                                                            val, stat
                                                        ) in char.passive_bonuses"
                                                        :key="stat"
                                                        class="advisor-upgrade-tag"
                                                        >+{{ val }}
                                                        {{
                                                            stat
                                                                .charAt(0)
                                                                .toUpperCase() +
                                                            stat.slice(1)
                                                        }}</span
                                                    >
                                                </div>

                                                <!-- Dice rows -->
                                                <div class="advisor-dice-rows">
                                                    <div
                                                        v-for="(
                                                            die, dIdx
                                                        ) in char.dice"
                                                        :key="dIdx"
                                                        class="advisor-die-row"
                                                    >
                                                        <span
                                                            class="advisor-die-label"
                                                            >DIE
                                                            {{ dIdx + 1 }}</span
                                                        >
                                                        <div
                                                            class="advisor-die-faces"
                                                        >
                                                            <span
                                                                v-for="(
                                                                    face, fIdx
                                                                ) in die"
                                                                :key="fIdx"
                                                                class="advisor-die-face"
                                                                :class="{
                                                                    'face-wild':
                                                                        face ===
                                                                        'WILD',
                                                                    'face-upgraded':
                                                                        char.level >
                                                                            0 &&
                                                                        isDiceUpgraded(
                                                                            char,
                                                                            dIdx,
                                                                            fIdx,
                                                                        ),
                                                                }"
                                                                >{{
                                                                    face ===
                                                                    "WILD"
                                                                        ? "W"
                                                                        : face
                                                                }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- WILD / ability callout -->
                                                <div class="advisor-ability">
                                                    <span
                                                        class="advisor-wild-tag"
                                                        >WILD
                                                        {{
                                                            char.wild_value
                                                        }}</span
                                                    >
                                                    <div
                                                        class="advisor-ability-text"
                                                    >
                                                        <span
                                                            class="advisor-ability-name"
                                                            >{{
                                                                char.wild_ability
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                char.wild_ability_description
                                                            "
                                                            class="advisor-ability-desc"
                                                            >{{
                                                                char.wild_ability_description
                                                            }}</span
                                                        >
                                                    </div>
                                                </div>

                                                <!-- PLAY AS X -->
                                                <button
                                                    class="ta-cta advisor-play-cta"
                                                    @click="
                                                        selectCharacter(char.id)
                                                    "
                                                >
                                                    PLAY AS
                                                    {{
                                                        (
                                                            char.display_name ||
                                                            char.name
                                                        ).toUpperCase()
                                                    }}
                                                </button>
                                            </div>
                                        </SwiperSlide>
                                    </Swiper>
                                </div>

                                <div class="advisor-hint">
                                    Swipe or drag to compare advisors — their
                                    dice are the hand you play with.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </template>
    </div>
</template>

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import {
    computed,
    inject,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import type { LocationQueryValue } from "vue-router";
import { useAuth } from "../stores/auth";
import { useToast } from "../stores/toast";
import { useFreeChest } from "../stores/free-chest";
import { playSound } from "../sounds";
import AnnouncementsBanner from "./AnnouncementsBanner.vue";
import DailyChallengeBanner from "./DailyChallengeBanner.vue";
import WeeklyChallengeBanner from "./WeeklyChallengeBanner.vue";
import RotatingEventBanner from "./RotatingEventBanner.vue";
import HomeHero from "./HomeHero.vue";
import LoginRegister from "./LoginRegister.vue";
import MatchmakingQueue from "./MatchmakingQueue.vue";
import StoryIntro from "./StoryIntro.vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { EffectCards } from "swiper/modules";
import type { Swiper as SwiperInstance } from "swiper";
import "swiper/css";
import "swiper/css/effect-cards";

interface StartingBonus {
    extra_dice?: number;
    random_item?: boolean;
    stat_boosts?: Record<string, number>;
}

interface Character {
    id: number;
    name: string;
    display_name?: string;
    description?: string;
    image_url?: string;
    level: number;
    dice: (string | number)[][];
    base_dice?: (string | number)[][];
    wild_value?: number;
    wild_ability?: string;
    wild_ability_description?: string;
    starting_bonus?: StartingBonus;
    extra_item_slots: number;
    card_redraws: number;
    passive_bonuses?: Record<string, number>;
    is_locked_for_user?: boolean;
}

interface GameInvite {
    id: number;
    sender?: { name?: string };
}

interface FriendEntry {
    id: number;
    user: { id: number; name?: string };
}

interface HouseRules {
    no_negative_effects: boolean;
    double_positive_effects: boolean;
    random_starting_stats: boolean;
    hardcore_mode: boolean;
}

interface RotatingEventData {
    game_type?: string;
    game_mode?: string;
    total_rounds?: number;
    character_pool?: number[];
}

interface GamePayload {
    game_mode: string;
    game_type: string;
    num_players: number;
    total_rounds: number | undefined;
    rotating_event_id?: number;
    bot_difficulty?: string;
    is_custom?: boolean;
    starting_stats?: number;
    house_rules?: HouseRules;
    is_private?: boolean;
    lobby_password?: string;
}

interface StartPayload {
    characters: (number | undefined)[];
    bot_difficulty?: string;
}

const auth = useAuth();
const toast = useToast();
const freeChest = useFreeChest();
const router = useRouter();
const route = useRoute();

function noop(): void {
    // Fallback when the parent does not provide the handler.
}

const openRules = inject<() => void>("openRules", noop);
const openTutorial = inject<() => void>("openTutorial", noop);

// War-table centre art — admin-configurable via Site Appearance, falling back
// to the bundled cover art.
const hubCenterImage = inject(
    "hubCenterImageUrl",
    ref<string | undefined>(undefined),
);
const discStyle = computed(() => ({
    backgroundImage: `radial-gradient(circle at 50% 40%, rgba(255,220,150,.18), rgba(0,0,0,.72) 74%), url(${hubCenterImage.value || "/images/cover-art.png"})`,
}));

const step = ref("mode");
const gameMode = ref("single");
const gameType = ref("cooperative");
const numberPlayers = ref(2);
const totalRounds = ref<number | undefined>(undefined);
const gameId = ref<number | undefined>(undefined);
const characters = ref<Character[]>([]);
const loading = ref(false);
const starting = ref(false);
// Carousel state
const currentPickingPlayer = ref(1);
const playerSelections = ref<Record<number, number>>({});
const swiperInstance = ref<SwiperInstance | undefined>(undefined);
const activeSlideIndex = ref(0);
const pendingInvites = ref<GameInvite[]>([]);
// Online friends picker
const availableFriends = ref<FriendEntry[]>([]);
const pendingReceivedFriends = ref<FriendEntry[]>([]);
const selectedFriendIds = ref<number[]>([]);
const friendsLoading = ref(false);
const addFriendUsername = ref("");
const addFriendError = ref("");
const addFriendSuccess = ref("");
const botDifficulty = ref("medium");
// Home screen stats
const homeStats = reactive({
    level: 1,
    elo: 1000,
    unclaimed: 0,
    activeGames: 0,
});
const notifCount = ref(0);
const showMobileMenu = ref(false);
// Custom game
const isCustomGame = ref(false);
const customStartingStats = ref(8);
const houseRules = reactive<HouseRules>({
    no_negative_effects: false,
    double_positive_effects: false,
    random_starting_stats: false,
    hardcore_mode: false,
});
// Rotating event
const rotatingEventId = ref<number | undefined>(undefined);
const rotatingEventData = ref<RotatingEventData | undefined>(undefined);
// Private lobby
const isPrivateGame = ref(false);
const lobbyPassword = ref("");
// Lobby browser
// (game length is now controlled by GameRule on the backend)

const swiperModules = [EffectCards];

const availableCharacters = computed(() => {
    if (gameType.value === "duel") {
        // Duel: both players can pick the same character
        return characters.value.filter((c) => !c.is_locked_for_user);
    }
    const selectedIds = Object.values(playerSelections.value);
    return characters.value.filter(
        (c) => !selectedIds.includes(c.id) && !c.is_locked_for_user,
    );
});

const allPlayersPicked = computed(() => {
    // Single-player duel: only 1 character needed (bot gets assigned automatically)
    const needed =
        gameMode.value === "single" && gameType.value === "duel"
            ? 1
            : numberPlayers.value;
    return Object.keys(playerSelections.value).length >= needed;
});

watch(
    () => route.fullPath,
    () => {
        if (route.path !== "/") {
            return;
        }

        const eventId = firstQueryValue(route.query.event_id);
        if (eventId === undefined) {
            resetToHome();
        } else {
            handleEventParameter(eventId);
        }
    },
);

onMounted(async () => {
    if (!auth.state.user) {
        return;
    }

    await fetchPendingInvites();
    subscribeToInvites();
    void fetchHomeStats();
    void freeChest.fetchStatus();
    // Check for rotating event query param
    const eventId = firstQueryValue(route.query.event_id);
    if (eventId !== undefined) {
        handleEventParameter(eventId);
    }
    // Check for resume query param (game stuck in setup)
    const resume = firstQueryValue(route.query.resume);
    if (resume !== undefined) {
        void resumeSetup(resume);
    }
});

onBeforeUnmount(() => {
    if (auth.state.user) {
        getEcho()?.leave(`user.${auth.state.user.id}`);
    }
});

function firstQueryValue(
    value: LocationQueryValue | LocationQueryValue[] | undefined,
): string | undefined {
    const single = Array.isArray(value) ? value[0] : value;
    return single ?? undefined;
}

interface EchoChannel {
    listen: (
        event: string,
        callback: (data: { game_id: number }) => void,
    ) => EchoChannel;
}

interface EchoInstance {
    private: (channel: string) => EchoChannel;
    leave: (channel: string) => void;
}

function getEcho(): EchoInstance | undefined {
    return (window as unknown as { Echo?: EchoInstance }).Echo;
}

function handleEventParameter(eventId: string): void {
    // Reset state but preserve event context
    step.value = "mode";
    gameId.value = undefined;
    characters.value = [];
    currentPickingPlayer.value = 1;
    playerSelections.value = {};
    activeSlideIndex.value = 0;
    // Set event and fetch details
    rotatingEventId.value = Number.parseInt(eventId);
    rotatingEventData.value = undefined;
    void fetchRotatingEvent(rotatingEventId.value);
}

async function fetchRotatingEvent(eventId: number): Promise<void> {
    try {
        const response = await axios.get<{ event: RotatingEventData }>(
            `/api/rotating-events/${eventId}`,
        );
        rotatingEventData.value = response.data.event;
        // Auto-set game type and mode from event
        if (rotatingEventData.value.game_type)
            gameType.value = rotatingEventData.value.game_type;
        if (rotatingEventData.value.game_mode)
            gameMode.value = rotatingEventData.value.game_mode;
        // Override total rounds if event specifies it
        if (rotatingEventData.value.total_rounds) {
            totalRounds.value = rotatingEventData.value.total_rounds;
        }
        // Auto-advance to settings step
        step.value = "settings";
    } catch {
        // ignore fetch errors
    }
}

async function fetchHomeStats(): Promise<void> {
    try {
        const [statsResponse, achResponse, historyResponse, unreadResponse] =
            await Promise.allSettled([
                axios.get<{ level?: number; elo_rating?: number }>(
                    "/api/auth/stats",
                ),
                axios.get<{ earned?: boolean; claimed?: boolean }[]>(
                    "/api/achievements",
                ),
                axios.get<{ active_games?: unknown[] }>("/api/games/history"),
                axios.get<{ count?: number }>(
                    "/api/notifications/unread-count",
                ),
            ]);

        if (statsResponse.status === "fulfilled") {
            const s = statsResponse.value.data;
            homeStats.level = s.level || 1;
            homeStats.elo = s.elo_rating || 1000;
        }

        if (achResponse.status === "fulfilled") {
            homeStats.unclaimed = achResponse.value.data.filter(
                (a) => a.earned && !a.claimed,
            ).length;
        }

        if (historyResponse.status === "fulfilled") {
            homeStats.activeGames = (
                historyResponse.value.data.active_games || []
            ).length;
        }

        // Notification badge: pending invites + unread DB notifications
        const databaseUnread =
            unreadResponse.status === "fulfilled"
                ? unreadResponse.value.data?.count || 0
                : 0;
        notifCount.value = pendingInvites.value.length + databaseUnread;
    } catch {
        // ignore stats errors
    }
}

async function fetchPendingInvites(): Promise<void> {
    try {
        const response = await axios.get<GameInvite[]>(
            "/api/game-invites/pending",
        );
        pendingInvites.value = response.data;
    } catch {
        // silently fail
    }
}

function subscribeToInvites(): void {
    const echo = getEcho();
    if (!echo || !auth.state.user) return;
    echo.private(`user.${auth.state.user.id}`)
        .listen("GameInviteReceived", () => {
            void fetchPendingInvites();
            notifCount.value++;
        })
        .listen("FriendRequestReceived", () => {
            notifCount.value++;
        })
        .listen("UserNotificationReceived", () => {
            notifCount.value++;
        })
        .listen("MatchFound", (data) => {
            if (step.value !== "matchmaking") {
                router.push(`/game/${data.game_id}`);
            }
        });
}

async function acceptInvite(invite: GameInvite): Promise<void> {
    try {
        const response = await axios.post<{ game_id: number }>(
            `/api/game-invites/${invite.id}/accept`,
        );
        router.push(`/game/${response.data.game_id}`);
    } catch (error) {
        toast.error(inviteErrorMessage(error) || "Failed to accept invite");
    }
}

async function declineInvite(invite: GameInvite): Promise<void> {
    try {
        await axios.post(`/api/game-invites/${invite.id}/decline`);
        pendingInvites.value = pendingInvites.value.filter(
            (index) => index.id !== invite.id,
        );
    } catch (error) {
        toast.error(inviteErrorMessage(error) || "Failed to decline invite");
    }
}

function inviteErrorMessage(error: unknown): string | undefined {
    if (isAxiosError<{ error?: string }>(error)) {
        return error.response?.data?.error;
    }
    return undefined;
}

function selectMode(): void {
    if (gameMode.value === "online") {
        // Online is always a duel — straight to the find-opponent screen, which owns
        // turn-length selection and the search itself.
        numberPlayers.value = 2;
        gameType.value = "duel";
        totalRounds.value = 24;
        step.value = "matchmaking";
        return;
    }
    numberPlayers.value = gameMode.value === "single" ? 1 : 2;
    gameType.value = "cooperative";
    step.value = "gameType";
}

async function fetchFriendsForPicker(): Promise<void> {
    friendsLoading.value = true;
    try {
        const response = await axios.get<{
            friends: FriendEntry[];
            pending_received?: FriendEntry[];
        }>("/api/friends");
        availableFriends.value = response.data.friends;
        pendingReceivedFriends.value = response.data.pending_received || [];
    } catch {
        availableFriends.value = [];
        pendingReceivedFriends.value = [];
    }
    friendsLoading.value = false;
}

async function acceptFriendInline(friendshipId: number): Promise<void> {
    try {
        await axios.post(`/api/friends/${friendshipId}/accept`);
        await fetchFriendsForPicker();
    } catch (error) {
        addFriendError.value = friendErrorMessage(error) || "Failed to accept";
    }
}

function toggleFriend(userId: number): void {
    const index = selectedFriendIds.value.indexOf(userId);
    if (index === -1) {
        if (selectedFriendIds.value.length < 5) {
            selectedFriendIds.value.push(userId);
        }
    } else {
        selectedFriendIds.value.splice(index, 1);
    }
}

async function addFriendInline(): Promise<void> {
    if (!addFriendUsername.value.trim()) return;
    addFriendError.value = "";
    addFriendSuccess.value = "";
    try {
        await axios.post("/api/friends", {
            username: addFriendUsername.value.trim(),
        });
        addFriendSuccess.value = `Request sent to ${addFriendUsername.value}`;
        addFriendUsername.value = "";
        await fetchFriendsForPicker();
    } catch (error) {
        addFriendError.value =
            friendErrorMessage(error) || "Failed to send request";
    }
}

function friendErrorMessage(error: unknown): string | undefined {
    if (isAxiosError<{ message?: string }>(error)) {
        return error.response?.data?.message;
    }
    return undefined;
}

function onMatchFound(matchedGameId: number): void {
    router.push(`/game/${matchedGameId}`);
}

function onCustomToggle(): void {
    // Reset when toggling off
    if (isCustomGame.value) {
        return;
    }

    customStartingStats.value = 8;
    Object.assign(houseRules, {
        no_negative_effects: false,
        double_positive_effects: false,
        random_starting_stats: false,
        hardcore_mode: false,
    });
}

async function gatherAdvisors(): Promise<void> {
    loading.value = true;
    try {
        if (gameMode.value === "online" && gameType.value === "duel") {
            // Online duel: use matchmaking
            loading.value = false;
            step.value = "matchmaking";
            return;
        }
        if (gameMode.value === "online") {
            // Online cooperative: numberPlayers = selected friends + yourself
            numberPlayers.value = selectedFriendIds.value.length + 1;
            const onlinePayload: GamePayload = {
                game_mode: gameMode.value,
                game_type: gameType.value,
                num_players: numberPlayers.value,
                total_rounds: totalRounds.value,
            };
            if (rotatingEventId.value) {
                onlinePayload.rotating_event_id = rotatingEventId.value;
            }
            if (isCustomGame.value) {
                onlinePayload.is_custom = true;
                onlinePayload.starting_stats = customStartingStats.value;
                onlinePayload.house_rules = { ...houseRules };
            }
            if (isPrivateGame.value) {
                onlinePayload.is_private = true;
                onlinePayload.lobby_password = lobbyPassword.value;
            }
            const gameResponse = await axios.post<{ id: number }>(
                "/api/games",
                onlinePayload,
            );
            gameId.value = gameResponse.data.id;
            // Auto-invite selected friends
            for (const friendUserId of selectedFriendIds.value) {
                try {
                    await axios.post(`/api/games/${gameId.value}/invite`, {
                        user_id: friendUserId,
                    });
                } catch {
                    // silently skip if invite fails
                }
            }
            router.push(`/game/${gameId.value}`);
            return;
        }
        const gamePayload: GamePayload = {
            game_mode: gameMode.value,
            game_type: gameType.value,
            num_players: numberPlayers.value,
            total_rounds: totalRounds.value,
        };
        if (rotatingEventId.value) {
            gamePayload.rotating_event_id = rotatingEventId.value;
        }
        if (gameMode.value === "single" && gameType.value === "duel") {
            gamePayload.bot_difficulty = botDifficulty.value;
        }
        if (isCustomGame.value) {
            gamePayload.is_custom = true;
            gamePayload.starting_stats = customStartingStats.value;
            gamePayload.house_rules = { ...houseRules };
        }
        const [gameResponse, charsResponse] = await Promise.all([
            axios.post<{ id: number }>("/api/games", gamePayload),
            axios.get<Character[]>("/api/characters", {
                params: { game_type: gameType.value },
            }),
        ]);
        gameId.value = gameResponse.data.id;
        let allChars = charsResponse.data;
        // Filter characters if rotating event has character_pool
        if (rotatingEventData.value?.character_pool) {
            const allowedIds = rotatingEventData.value.character_pool;
            allChars = allChars.filter((c) => allowedIds.includes(c.id));
        }
        characters.value = allChars;
        step.value = gameMode.value === "single" ? "characters" : "story";
    } catch (error) {
        toast.error(
            "Failed to create game: " + gameErrorMessage(error, "message"),
        );
    }
    loading.value = false;
}

function gameErrorMessage(error: unknown, key: "message" | "error"): string {
    if (isAxiosError<{ message?: string; error?: string }>(error)) {
        return error.response?.data?.[key] ?? error.message;
    }
    if (error instanceof Error) {
        return error.message;
    }
    return "Something went wrong";
}

function onSwiper(swiper: SwiperInstance): void {
    swiperInstance.value = swiper;
}

function onSlideChange(swiper: SwiperInstance): void {
    activeSlideIndex.value = swiper.activeIndex;
}

function selectCharacter(charId: number): void {
    playSound("clickCard");
    playerSelections.value[currentPickingPlayer.value] = charId;
    const pickCount =
        gameMode.value === "single" && gameType.value === "duel"
            ? 1
            : numberPlayers.value;
    if (currentPickingPlayer.value < pickCount) {
        currentPickingPlayer.value++;
        void nextTick(() => {
            activeSlideIndex.value = 0;
            if (swiperInstance.value) {
                swiperInstance.value.slideTo(0, 0);
            }
        });
    }
}

function removePlayerSelection(playerNumber: number): void {
    playerSelections.value = Object.fromEntries(
        Object.entries(playerSelections.value).filter(
            ([key]) => Number(key) !== playerNumber,
        ),
    );
}

function undoLastPick(): void {
    const pickCount =
        gameMode.value === "single" && gameType.value === "duel"
            ? 1
            : numberPlayers.value;
    // Remove the last pick and go back to picking
    currentPickingPlayer.value = pickCount;
    removePlayerSelection(currentPickingPlayer.value);
    void nextTick(() => {
        activeSlideIndex.value = 0;
        if (swiperInstance.value) {
            swiperInstance.value.slideTo(0, 0);
        }
    });
}

function hasUpgradeBonuses(char: Character): boolean {
    return (
        char.extra_item_slots > 0 ||
        char.card_redraws > 0 ||
        Object.keys(char.passive_bonuses || {}).length > 0
    );
}

function isDiceUpgraded(
    char: Character,
    dieIndex: number,
    faceIndex: number,
): boolean {
    if (!char.base_dice) return false;
    const baseValue = char.base_dice[dieIndex]?.[faceIndex];
    const moduleValue = char.dice[dieIndex]?.[faceIndex];
    return baseValue !== moduleValue;
}

function getCharacterBonusLabel(charId: number): string {
    const char = characters.value.find((c) => c.id === charId);
    if (!char?.starting_bonus) return "";
    const parts: string[] = [];
    const b = char.starting_bonus;
    if (b.extra_dice)
        parts.push(
            `+${b.extra_dice} Extra ${b.extra_dice === 1 ? "Die" : "Dice"}`,
        );
    if (b.random_item) parts.push("Random Item");
    if (b.stat_boosts) {
        for (const [stat, value] of Object.entries(b.stat_boosts)) {
            const label = stat.charAt(0).toUpperCase() + stat.slice(1);
            parts.push(`${value > 0 ? "+" : ""}${value} ${label}`);
        }
    }
    return parts.join(", ");
}

function getCharacterName(charId: number): string {
    const char = characters.value.find((c) => c.id === charId);
    return char ? char.name : "Unknown";
}

function getCharacterImage(charId: number): string {
    const char = characters.value.find((c) => c.id === charId);
    return char?.image_url || "/images/character.png";
}

function goBack(): void {
    if (step.value === "gameType") {
        step.value = "mode";
        return;
    }
    if (step.value === "settings") {
        goBackFromSettings();
        return;
    }
    if (step.value === "matchmaking" || step.value === "story") {
        step.value = "settings";
        return;
    }
    if (step.value === "characters") {
        goBackFromCharacters();
    }
}

function goBackFromSettings(): void {
    if (rotatingEventId.value) {
        // Event game: go back to home and clear event
        rotatingEventId.value = undefined;
        rotatingEventData.value = undefined;
        router.replace("/");
        step.value = "mode";
        return;
    }
    if (gameMode.value === "online" || gameMode.value === "single") {
        // Online skips gameType (goes straight to duel), single also goes to mode
        step.value = "mode";
        return;
    }
    step.value = "gameType";
}

function goBackFromCharacters(): void {
    if (currentPickingPlayer.value > 1) {
        // Go back one player pick
        currentPickingPlayer.value--;
        removePlayerSelection(currentPickingPlayer.value);
        void nextTick(() => {
            activeSlideIndex.value = 0;
            if (swiperInstance.value) {
                swiperInstance.value.slideTo(0, 0);
            }
        });
        return;
    }
    playerSelections.value = {};
    step.value = gameMode.value === "single" ? "settings" : "story";
}

function resetToHome(): void {
    step.value = "mode";
    gameId.value = undefined;
    characters.value = [];
    currentPickingPlayer.value = 1;
    playerSelections.value = {};
    rotatingEventId.value = undefined;
    rotatingEventData.value = undefined;
    activeSlideIndex.value = 0;
    void fetchHomeStats();
}

async function resumeSetup(gameIdToResume: string): Promise<void> {
    try {
        const [gameResponse, charsResponse] = await Promise.all([
            axios.get<{
                game: {
                    id: number;
                    status: string;
                    game_mode: string;
                    game_type?: string;
                    num_players: number;
                    total_rounds?: number;
                };
            }>(`/api/games/${gameIdToResume}`),
            axios.get<Character[]>("/api/characters"),
        ]);
        const game = gameResponse.data.game;
        if (game.status !== "setup") return; // game already started
        gameId.value = game.id;
        gameMode.value = game.game_mode;
        gameType.value = game.game_type || "cooperative";
        numberPlayers.value = game.num_players;
        totalRounds.value = game.total_rounds;
        characters.value = charsResponse.data;
        currentPickingPlayer.value = 1;
        playerSelections.value = {};
        step.value = "characters";
    } catch {
        // If resume fails, just stay on home
    }
}

async function startGame(): Promise<void> {
    starting.value = true;
    try {
        const selectedIds: (number | undefined)[] = [];
        const pickCount =
            gameMode.value === "single" && gameType.value === "duel"
                ? 1
                : numberPlayers.value;
        for (let index = 1; index <= pickCount; index++) {
            selectedIds.push(playerSelections.value[index]);
        }
        const startPayload: StartPayload = { characters: selectedIds };
        if (gameMode.value === "single" && gameType.value === "duel") {
            startPayload.bot_difficulty = botDifficulty.value;
        }
        await axios.post(`/api/games/${gameId.value}/start`, startPayload);
        router.push(`/game/${gameId.value}`);
    } catch (error) {
        toast.error("Failed to start: " + gameErrorMessage(error, "error"));
    }
    starting.value = false;
}
</script>

<style scoped>
/* Fade transition between steps */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.35s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.setup-screen {
    max-width: 800px;
    margin: 0 auto;
    flex: 1;
    min-height: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.story-step {
    height: 100%;
}

.auth-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 60px 0;
    color: var(--text-secondary);
    font-style: italic;
    font-size: 1.1rem;
}

.section-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 15px;
    text-align: center;
    text-shadow:
        0 2px 4px rgba(0, 0, 0, 0.6),
        0 0 20px rgba(240, 192, 80, 0.1);
}

.flavor-text {
    text-align: center;
    font-style: italic;
    color: var(--text-secondary);
    margin-bottom: 25px;
    line-height: 1.6;
    font-size: 1.1rem;
}

/* ====================================================================
 * HOME HUB — the war table
 * ==================================================================== */
.home-hub {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.hub-banners {
    flex: none;
}

.daily-enhanced {
    margin-bottom: 10px;
}

/* --- The circular war table with orbiting seats --- */
.war-table {
    position: relative;
    flex: none;
    width: 100%;
    max-width: 360px;
    margin: 6px auto 0;
    /* Square aspect so the disc + seats keep their geometry as the screen scales. */
    aspect-ratio: 1 / 1;
}

.war-table-disc {
    position: absolute;
    left: 50%;
    top: 4%;
    transform: translateX(-50%);
    width: 88%;
    aspect-ratio: 1 / 1;
    border-radius: 50%;
}

.war-table-disc {
    background-image:
        radial-gradient(
            circle at 50% 40%,
            rgba(255, 220, 150, 0.18),
            rgba(0, 0, 0, 0.72) 74%
        ),
        url(/images/cover-art.png);
    background-size: 150%;
    background-position: center 72%;
    border: 2px solid rgba(200, 149, 46, 0.55);
}

/* --- Central PLAY crest --- */
.table-crest {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 42%;
    aspect-ratio: 1 / 1;
    border-radius: 50%;
    background: radial-gradient(
        circle at 50% 34%,
        #ffe897,
        #f0c050 46%,
        #8f6516
    );
    border: 3px solid #fff0b0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
    cursor: pointer;
    padding: 0;
    transition:
        transform 0.12s,
        box-shadow 0.12s;
}

.table-crest:active {
    transform: translate(-50%, -50%) translateY(4px);
}

.crest-glyph {
    font-size: 1.9rem;
    line-height: 1;
    color: #2a1f0a;
}

.crest-title {
    font-family: "Cinzel", serif;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 1.6px;
    color: #241703;
}

.crest-sub {
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    color: #5e441a;
}

/* --- Seat medallions --- */
.table-seat {
    position: absolute;
    transform: translate(-50%, -50%);
    width: 26%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
}

.seat-tl {
    left: 14%;
    top: 25%;
}
.seat-tr {
    left: 86%;
    top: 25%;
}
.seat-bl {
    left: 14%;
    top: 72%;
}
.seat-br {
    left: 86%;
    top: 72%;
}

.seat-medallion {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--accent-gold);
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.96),
        rgba(14, 10, 6, 0.96)
    );
    border: 2px solid rgba(240, 192, 80, 0.4);
    box-shadow:
        0 5px 0 rgba(0, 0, 0, 0.55),
        inset 0 1px 0 rgba(255, 220, 140, 0.18);
    transition:
        transform 0.12s,
        box-shadow 0.12s,
        border-color 0.12s;
}

.table-seat:hover .seat-medallion {
    border-color: rgba(240, 192, 80, 0.85);
    transform: translateY(-2px);
    box-shadow:
        0 7px 0 rgba(0, 0, 0, 0.55),
        0 0 16px rgba(240, 192, 80, 0.3),
        inset 0 1px 0 rgba(255, 220, 140, 0.18);
}

.table-seat:active .seat-medallion {
    transform: translateY(3px);
    box-shadow:
        0 2px 0 rgba(0, 0, 0, 0.55),
        inset 0 1px 0 rgba(255, 220, 140, 0.18);
}

.seat-glyph {
    line-height: 1;
}

.seat-dot {
    position: absolute;
    top: -1px;
    right: -1px;
}

.seat-label-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    padding: 4px 8px;
    border-radius: 9px;
    background: rgba(8, 6, 3, 0.85);
    border: 1px solid rgba(240, 192, 80, 0.24);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
    max-width: 100%;
}

.seat-label {
    font-family: "Cinzel", serif;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 1.1px;
    color: #f0dcb0;
    white-space: nowrap;
}

.seat-meta {
    font-size: 0.62rem;
    color: #bcac8c;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 90px;
}

/* --- The ledger drawer --- */
.hub-ledger {
    flex: 1;
    min-height: 0;
    margin-top: 2px;
    padding: 4px 16px 16px;
    background: linear-gradient(
        180deg,
        rgba(20, 14, 8, 0.94),
        rgba(11, 8, 5, 0.98)
    );
    border-top: 1px solid rgba(240, 192, 80, 0.3);
    border-radius: 22px 22px 0 0;
    box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.6);
}

.hub-ledger .ta-divider {
    margin-top: 10px;
}

.ledger-row {
    cursor: pointer;
    transition:
        border-color 0.15s,
        transform 0.12s;
}

.ledger-row:hover {
    border-color: rgba(240, 192, 80, 0.5);
    transform: translateY(-1px);
}

.ledger-art {
    width: 44px;
    height: 44px;
    flex: none;
    border-radius: 10px;
    border: 1px solid rgba(240, 192, 80, 0.4);
    background-image:
        radial-gradient(
            circle at 50% 40%,
            rgba(255, 220, 150, 0.12),
            rgba(0, 0, 0, 0.5) 80%
        ),
        url(/images/cover-art.png);
    background-size: 230%;
    background-position: 44% 72%;
}

.ledger-medallion {
    position: relative;
}

.medallion-dot {
    position: absolute;
    top: -4px;
    right: -4px;
}

.ledger-medallion .ta-medallion-face {
    position: relative;
}

.solo-cta {
    margin-top: 12px;
}

/* Pending invites in the ledger */
.invites-panel {
    margin-top: 6px;
}

.invite-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.invite-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(154, 208, 255, 0.35);
    border-radius: 13px;
}

.invite-from {
    color: var(--text-bright, #f0e6d2);
    font-size: 0.9rem;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.invite-actions {
    display: flex;
    gap: 8px;
    flex: none;
}

.btn-sm {
    padding: 4px 14px;
    font-size: 0.85rem;
    border-radius: 6px;
}

.btn-decline {
    background: rgba(160, 48, 32, 0.2);
    color: #d05040;
    border: 1px solid rgba(160, 48, 32, 0.4);
    cursor: pointer;
}

/* ====================================================================
 * SETTINGS / GAME-TYPE steps (secondary, on-theme)
 * ==================================================================== */
.mode-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
}

.mode-card {
    background: linear-gradient(
        180deg,
        var(--wood-light),
        var(--wood-medium),
        var(--wood-dark)
    );
    border: 2px solid var(--border-gold);
    border-radius: 12px;
    padding: 16px 14px;
    cursor: pointer;
    transition: all 0.15s;
    box-sizing: border-box;
    width: 100%;
    text-align: center;
    box-shadow:
        0 4px 0 rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 220, 140, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.mode-card:hover {
    border-color: var(--accent-gold-bright);
    box-shadow:
        0 6px 0 rgba(0, 0, 0, 0.3),
        0 0 16px rgba(240, 192, 80, 0.2),
        inset 0 1px 0 rgba(255, 220, 140, 0.15);
    transform: translateY(-2px);
}

.mode-card:active {
    transform: translateY(3px);
    box-shadow:
        0 1px 0 rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 220, 140, 0.1);
}

.mode-title {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 2px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
}

.mode-subtitle {
    display: block;
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-style: italic;
}

.mode-desc {
    font-size: 0.8rem;
    color: var(--text-secondary);
    line-height: 1.4;
    margin-top: 4px;
}

.player-select {
    text-align: center;
    margin-bottom: 20px;
}

.player-select label {
    display: block;
    margin-bottom: 10px;
    font-family: "Cinzel", serif;
    color: var(--text-bright);
}

.player-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.player-buttons button {
    width: 50px;
    height: 50px;
    font-size: 1.2rem;
    border-radius: 6px;
}

.bot-difficulty-select .player-buttons button {
    width: auto;
    min-width: 80px;
    padding: 8px 16px;
    font-size: 1rem;
}

/* Friends picker */
.friends-loading {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 20px;
}

.friends-picker {
    margin-bottom: 20px;
}

.add-friend-inline {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}

.friend-input {
    flex: 1;
    background: rgba(0, 0, 0, 0.3);
    border: 2px solid var(--border-gold);
    border-radius: 6px;
    color: var(--text-primary);
    font-family: "Crimson Text", Georgia, serif;
    font-size: 1rem;
    padding: 8px 12px;
    outline: none;
}

.friend-input:focus {
    border-color: var(--accent-gold);
}

.friend-error {
    color: var(--accent-red);
    font-size: 0.85rem;
    margin-bottom: 6px;
}

.friend-success {
    color: var(--accent-green);
    font-size: 0.85rem;
    margin-bottom: 6px;
}

.received-requests {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(138, 106, 46, 0.2);
}

.received-label {
    display: block;
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 0.85rem;
    margin-bottom: 8px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.received-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: rgba(67, 160, 212, 0.06);
    border: 1px solid rgba(67, 160, 212, 0.2);
    border-radius: 6px;
    margin-bottom: 6px;
}

.received-name {
    color: var(--text-bright, #f0e6d2);
    font-size: 0.95rem;
}

.no-friends {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 20px;
}

.friend-pick-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 12px;
}

.friend-pick-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: rgba(26, 18, 9, 0.6);
    border: 2px solid rgba(138, 106, 46, 0.2);
    border-radius: 8px;
    cursor: pointer;
    transition:
        border-color 0.2s,
        background 0.2s;
}

.friend-pick-row:hover {
    border-color: rgba(212, 168, 67, 0.4);
}

.friend-selected {
    border-color: var(--accent-gold);
    background: rgba(212, 168, 67, 0.08);
}

.friend-pick-check {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(138, 106, 46, 0.4);
    border-radius: 4px;
    color: var(--accent-gold);
    font-size: 0.85rem;
    font-weight: 700;
}

.friend-selected .friend-pick-check {
    border-color: var(--accent-gold);
    background: rgba(212, 168, 67, 0.2);
}

.friend-pick-name {
    color: var(--text-bright, #f0e6d2);
    font-family: "Cinzel", serif;
    font-size: 1rem;
}

.selected-count {
    text-align: center;
    color: var(--accent-gold);
    font-family: "Cinzel", serif;
    font-size: 0.9rem;
    padding: 8px;
    background: rgba(212, 168, 67, 0.06);
    border-radius: 6px;
}

.step-nav {
    display: flex;
    align-items: stretch;
    gap: 14px;
    margin-top: 25px;
}

.step-nav > button {
    flex: 1;
}

.back-btn {
    background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
    border: 2px solid rgba(138, 106, 46, 0.5);
    border-radius: 10px;
    color: var(--text-secondary);
    font-size: 1rem;
    padding: 12px 20px;
    cursor: pointer;
    letter-spacing: 0;
    box-shadow: 0 3px 0 rgba(0, 0, 0, 0.25);
}

.back-btn:hover {
    color: var(--text-bright);
    border-color: var(--border-gold);
    background: linear-gradient(180deg, #4a3a24, var(--wood-light));
    box-shadow:
        0 3px 0 rgba(0, 0, 0, 0.25),
        0 0 8px rgba(240, 192, 80, 0.1);
    transform: translateY(-1px);
}

.back-btn:active {
    transform: translateY(2px);
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.25);
}

.start-btn {
    font-size: 1.25rem;
    padding: 14px 24px;
    text-transform: uppercase;
    letter-spacing: 2px;
}

/* ====================================================================
 * ADVISOR PICKER (drawer chrome + reskinned card)
 * ==================================================================== */
.advisors-page {
    max-width: 480px;
    margin: 0 auto;
}

.picking-screen {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.carousel-wrapper {
    width: 100%;
    max-width: 340px;
    margin: 0 auto 12px;
    padding: 12px 0;
}

.advisor-hint {
    text-align: center;
    font-size: 0.82rem;
    color: var(--ta-mute-dim);
    padding: 0 8px;
}

/* Advisor card inside swiper — mock treatment */
.advisor-card {
    display: flex;
    flex-direction: column;
    width: 320px;
    min-height: 480px;
    padding: 14px;
    box-sizing: border-box;
    border-radius: 16px;
    background: linear-gradient(
        180deg,
        rgba(58, 42, 26, 0.92),
        rgba(14, 10, 6, 0.96)
    );
    border: 1.5px solid rgba(240, 192, 80, 0.5);
    box-shadow:
        0 5px 0 rgba(0, 0, 0, 0.5),
        0 8px 32px rgba(0, 0, 0, 0.6);
}

.advisor-head {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.advisor-portrait-wrap {
    width: 88px;
    height: 88px;
    flex: none;
    border-radius: 50%;
    overflow: visible;
    border: 2.5px solid var(--accent-gold);
    box-shadow: 0 0 18px rgba(240, 192, 80, 0.3);
    position: relative;
}

.advisor-level-pip {
    position: absolute;
    bottom: -4px;
    right: -4px;
    background: linear-gradient(135deg, var(--accent-gold), #b08830);
    color: #1a0f05;
    font-size: 0.7rem;
    font-weight: 800;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #1a1209;
    font-family: "Cinzel", serif;
    z-index: 1;
}

.advisor-portrait {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.advisor-head-text {
    flex: 1;
    min-width: 0;
}

.advisor-name {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 1.15rem;
    font-weight: 800;
    line-height: 1.1;
}

.advisor-role {
    margin-top: 2px;
    font-family: "Cinzel", serif;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 1.4px;
    color: var(--ta-mute-dim);
}

.advisor-desc {
    margin-top: 5px;
    color: #bcac8c;
    font-size: 0.8rem;
    line-height: 1.4;
    font-style: italic;
}

/* Bonus + upgrade tags */
.advisor-bonus-badge {
    margin-top: 10px;
    background: rgba(212, 168, 67, 0.15);
    border: 1px solid rgba(212, 168, 67, 0.4);
    border-radius: 6px;
    padding: 4px 10px;
    color: var(--accent-gold);
    font-size: 0.78rem;
    font-weight: 600;
    text-align: center;
}

.advisor-upgrades {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: center;
}

.advisor-upgrade-tag {
    font-size: 0.62rem;
    padding: 2px 6px;
    background: rgba(90, 184, 122, 0.1);
    border: 1px solid rgba(90, 184, 122, 0.25);
    border-radius: 4px;
    color: #5ab87a;
}

/* Dice rows */
.advisor-dice-rows {
    margin-top: 11px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.advisor-die-row {
    display: flex;
    align-items: center;
    gap: 7px;
}

.advisor-die-label {
    width: 34px;
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.6rem;
    letter-spacing: 1px;
    color: var(--ta-mute-dim);
}

.advisor-die-faces {
    display: flex;
    gap: 5px;
    flex: 1;
}

.advisor-die-face {
    flex: 1;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: "Cinzel", serif;
    font-size: 0.88rem;
    font-weight: 800;
    border-radius: 9px;
    background: linear-gradient(180deg, #2e2214, #150f08);
    border: 1px solid rgba(240, 192, 80, 0.35);
    color: var(--ta-text);
    box-shadow: 0 2px 0 rgba(0, 0, 0, 0.45);
}

.advisor-die-face.face-wild {
    background: linear-gradient(180deg, #ffe897, #c8952e);
    border-color: #fff0b0;
    color: #241703;
}

.advisor-die-face.face-upgraded {
    color: #5ab87a;
    border-color: rgba(90, 184, 122, 0.6);
    background: linear-gradient(180deg, rgba(90, 184, 122, 0.2), #150f08);
}

/* WILD / ability callout */
.advisor-ability {
    margin-top: 11px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 11px;
    border-radius: 13px;
    background: rgba(240, 192, 80, 0.1);
    border: 1px solid rgba(240, 192, 80, 0.4);
}

.advisor-wild-tag {
    flex: none;
    font-family: "Cinzel", serif;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.8px;
    color: #241703;
    background: linear-gradient(180deg, #ffe897, #c8952e);
    border-radius: 9px;
    padding: 4px 9px;
}

.advisor-ability-text {
    flex: 1;
    min-width: 0;
}

.advisor-ability-name {
    display: block;
    font-family: "Cinzel", serif;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--ta-text);
    text-transform: capitalize;
}

.advisor-ability-desc {
    display: block;
    font-size: 0.72rem;
    color: var(--ta-mute);
}

.advisor-play-cta {
    margin-top: auto;
    padding-top: 13px;
    padding-bottom: 13px;
}

.advisor-card > .advisor-play-cta {
    margin-top: 13px;
}

/* Summary (council assembled) */
.summary-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.summary-picks {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-pick {
    align-items: center;
}

.summary-portrait-wrap {
    width: 48px;
    height: 48px;
    flex: none;
    border-radius: 50%;
    border: 2px solid var(--accent-gold);
    overflow: hidden;
}

.summary-portrait {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.summary-player {
    text-transform: uppercase;
    letter-spacing: 1px;
}

.summary-name {
    font-size: 1rem;
}

.summary-bonus {
    margin-top: 2px;
    font-size: 0.72rem;
    color: var(--accent-gold);
    font-style: italic;
}

.begin-cta {
    margin-top: 8px;
}

/* ====================================================================
 * Mobile menu overlay (kept)
 * ==================================================================== */
.mobile-menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mobile-menu-panel {
    background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
    border: 2px solid var(--border-gold);
    border-radius: 14px;
    padding: 10px 0;
    min-width: 220px;
    box-shadow:
        0 4px 0 rgba(0, 0, 0, 0.3),
        0 10px 40px rgba(0, 0, 0, 0.7);
}

.mobile-menu-item {
    display: block;
    width: 100%;
    padding: 14px 28px;
    background: none;
    border: none;
    border-radius: 0;
    color: var(--text-primary);
    font-family: "Cinzel", serif;
    font-size: 1.05rem;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    transition:
        background 0.2s,
        color 0.2s;
    letter-spacing: 1px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    box-shadow: none;
}

.mobile-menu-item:hover {
    background: rgba(240, 192, 80, 0.1);
    color: var(--accent-gold);
    transform: none;
    box-shadow: none;
}

/* ====================================================================
 * Custom / private game (kept)
 * ==================================================================== */
.custom-game-section {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(138, 106, 46, 0.2);
}

.custom-warning {
    margin: 8px 0 0;
    padding: 8px 12px;
    background: rgba(200, 160, 40, 0.12);
    border: 1px solid rgba(200, 160, 40, 0.3);
    border-radius: 6px;
    color: #c0a030;
    font-size: 0.8rem;
    text-align: center;
}

.custom-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    margin-bottom: 10px;
}

.custom-toggle input[type="checkbox"] {
    accent-color: var(--accent-gold);
    width: 18px;
    height: 18px;
}

.custom-toggle-label {
    font-family: "Cinzel", serif;
    color: var(--accent-gold);
    font-size: 0.9rem;
    font-weight: 600;
}

.custom-options {
    padding: 12px 16px;
    background: rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(138, 106, 46, 0.15);
    border-radius: 8px;
}

.custom-option {
    margin-bottom: 14px;
}

.custom-option label {
    display: block;
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 0.8rem;
    margin-bottom: 6px;
}

.custom-slider {
    width: 100%;
    accent-color: var(--accent-gold);
}

.hr-label {
    font-family: "Cinzel", serif;
    color: var(--text-bright);
    font-size: 0.85rem;
    margin-bottom: 8px;
}

.hr-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-size: 0.82rem;
    margin-bottom: 6px;
    cursor: pointer;
}

.hr-toggle input[type="checkbox"] {
    accent-color: var(--accent-gold);
}

.private-section {
    margin-top: 14px;
}

.lobby-password-input {
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    border: 2px solid var(--border-gold);
    border-radius: 6px;
    color: var(--text-primary);
    font-family: "Crimson Text", Georgia, serif;
    font-size: 1rem;
    padding: 8px 12px;
    outline: none;
    margin-top: 6px;
    box-sizing: border-box;
}

.lobby-password-input:focus {
    border-color: var(--accent-gold);
}

/* ====================================================================
 * Mobile compact
 * ==================================================================== */
@media (max-width: 768px) {
    .section-title {
        font-size: 1.3rem;
        margin-bottom: 10px;
    }

    .flavor-text {
        font-size: 0.95rem;
        margin-bottom: 18px;
    }

    .mode-card {
        padding: 10px 10px;
    }

    .player-buttons button {
        width: 42px;
        height: 42px;
        font-size: 1rem;
    }

    .start-btn {
        font-size: 1rem;
        padding: 10px 30px;
    }

    .back-btn {
        font-size: 0.8rem;
        padding: 8px 16px;
    }

    .step-nav {
        margin-top: 18px;
        gap: 10px;
    }

    .carousel-wrapper {
        max-width: 300px;
        margin-bottom: 8px;
        padding: 8px 0;
    }

    .advisor-card {
        width: 288px;
        min-height: 440px;
        padding: 12px;
    }

    .advisor-portrait-wrap {
        width: 72px;
        height: 72px;
    }

    .advisor-name {
        font-size: 1.05rem;
    }

    .advisor-desc {
        font-size: 0.75rem;
    }

    .friend-pick-row {
        padding: 8px 12px;
    }

    .friend-pick-name {
        font-size: 0.9rem;
    }

    .selected-count {
        font-size: 0.8rem;
    }

    .custom-options {
        padding: 10px;
    }

    .war-table {
        max-width: 320px;
    }

    .seat-medallion {
        width: 52px;
        height: 52px;
        font-size: 1.2rem;
    }
}
</style>
