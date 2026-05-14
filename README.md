# ✅ HumanNPC
<center><img src='https://github.com/BeeAZ-pm-pl/HumanNPC/blob/master/icon.png'></img></center>

**A Simple NPC Plugin For PocketMine 5 !**

---

# ✅ Command:Permission & Help
💥 **/hnpc** : `humannpc.command`
<br>
💥 **/rca** : `humannpc.rca`
<br>
💡 Use **/hnpc help** : Show All Commands HNPC

---

# ✅ Notice
**The plugin only supports getting skins from players not supporting skin mobs**

---

# ✅ Detailed Guide & Examples

### 💡 Basic Management
💥 **Spawn NPC**: `/hnpc spawn (name)`
<br>
💥 **Get NPC ID**: `/hnpc id` *(Toggle mode, hit an NPC to get its ID)*
<br>
💥 **Delete NPC**: `/hnpc delete` *(Toggle mode, hit an NPC to remove it)*
<br>
💥 **List all NPCs**: `/hnpc npcs`
<br>
💥 **Teleport to NPC**: `/hnpc tp (id)`

### 💡 Editing Appearance
💥 **Change Name**: `/hnpc edit (id) rename (name)`
<br>
💥 **Set Item in Hand**: `/hnpc edit (id) settool` *(Must hold the item in your hand)*
<br>
I don't know why, but in the newer versions of Minecraft, when I use a skin on the server, it turns into the default skin. So I wanted to add this feature to help users quickly change the NPC appearance externally using a link.
<br>
💥 **Update Skin via URL**: `/hnpc edit (id) setskin (url)` *(Must be a direct .png link)*

### 💡 Click Commands
**Notice:** *Use `{player}` for the clicker's name*
<br>
💥 **Player Command (Using RCA)**: `/hnpc edit (id) addcmd rca {player} (command)`
> *Example: `/hnpc edit 1 addcmd rca {player} shop`*

💥 **Console Command (System)**: `/hnpc edit (id) addcmd (command)`
> *Example: `/hnpc edit 1 addcmd give {player} diamond_sword 1`*

💥 **View Assigned Commands**: `/hnpc edit (id) listcmd`
<br>
💥 **Remove a Command**: `/hnpc edit (id) removecmd (command)`
