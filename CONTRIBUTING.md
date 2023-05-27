# Guide de contribution à Maarch 

Tout d'abord, merci de prendre le temps de contribuer.

Voici un ensemble de lignes directrices pour contribuer à Maarch et ses produits, hébergés dans l'Organisation Maarch sur [Maarch GitLab](http://labs.maarch.org).

## Que dois-je savoir avant de commencer ?
### Contrat de contribution
Toute personne souhaitant contribuer à Maarch doit lire et signer notre [contrat de licence de contributeur](https://labs.maarch.org/maarch/MaarchCourrier/blob/master/CLA.md). 

L'équipe Maarch est légalement interdite d'accepter les demandes de fusion des utilisateurs qui n'ont pas encore signé le CLA.

### A propos de Maarch
Les solutions Maarch sont des outils de gestion électronique de documents professionnel qui répondent nativement à la grande majorité des besoins en gestion opérationnelle des documents.

Ils sont publiés sous les termes de la licence gratuite et open source GNU / GPLv3. L'une des conséquences est que les logiciels Maarch sont abordables pour tout type d'organisation.

### Processus de gestion des contributions
Le succès de Maarch nous apporte de nombreux retours fonctionnels ou techniques de la part de sa communauté (utilisateurs, clients, partenaires, core team).
Maarch s'organise pour traiter au mieux ces demandes.

Selon les types de demandes ou contributions remontées, Maarch a mis en place les processus suivants :

#### Demande d'évolution
Toute demande FEAT doit être déclarée dans la forge, qu'elle soit finalement réalisée par un développeur de la core team Maarch ou un partenaire.

* Une FEAT doit être acceptée (ou refusée) avant de commencer son développement
* Comme il y a plusieurs manière d'adresser un même besoin, les développeurs discutent entre eux pour échanger sur le comment faire

#### Déclaration de bug
Tout BUG doit être déclaré et qualifié (notamment sur sa gravité) dans la forge.
* Un BUG doit être reproductible sur la version stable cible
* Si il y a une méthode de contournement temporaire possible (le temps de traiter proprement le bug), elle est indiquée dans la forge.

#### A propos de l'ingénierie logicielle
L'ingénierie logicielle Maarch est traitée depuis notre [gitlab](http://labs.maarch.org).
En voici les grands principes de fonctionnement :

##### Branche de développement
'develop' est la zone de jeu commune des développeurs de la core team Maarch :
* C'est ici qu'ils ajoutent leur code nouveau, expérimentent, suppriment le code inutile, explorent ensemble des pistes techniques et se challengent sur la bonne manière de faire.
* Bien évidement, tout code ajouté doit être accompagné de son test unitaire. N'y est donc intégré que du code relativement exploitable, utile au projet et aux autres développeurs.
* C'est une version non opérationnelle et non stable qui peut être modifiée plusieurs fois par heure.
* Cette branche ne préfigure pas d'une future version car tout peut y être remis en question à tout moment.
* Il peut y avoir un nombre illimité de branche créée à partir de celle-ci.

##### Master ou la future version
'master' est la release-candidate de la version future.
* La future version stable du produit se dessine donc dans le master. Elle est à tout moment opérationnelle, mais déclarée comme non-stable. Son code est une agrégation, sur la dernière version déclarée stable :

** du code nouveau/modifié de 'develop' déclaré comme "terminé et probablement stable" par les développeurs. On ne merge donc pas TOUT 'develop' mais seulement certaines parties validée.
** du code nouveau/modifié (et non totalement révolutionnaire) d'une commande client
** Toute demande de merge se fait par les développeurs et est acceptée (ou pas) par le "project master"

* Le lien des FEAT de la forge et GIT se donc dans le master.
* C'est sur cette branche qu'interviennent nos partenaires techniques pour les améliorations du produit (parce qu'on a validé la FEAT avec eux). Il faudra donc aussi les remonter dans 'develop' une fois accepté ici.
* C'est sur cette branche que se passent l'ensemble des tests unitaires et d'intégration.
* C'est à partir de cette branche qu'est géré l'internationalisation produite par notre [plateforme de traduction](http://translate.maarch.org/)

##### Version stable
'v19.x' (v18.x, v19.x, v20.x, …) est la version opérationnelle et stable de Maarch Courrier de l'année en cours

* Son code représente un instant T dans l'année de 'master' qu'on a jugé prêt et stable pour déploiement chez les utilisateurs.
* À sa création, c'est donc une branche directement issue de 'master' sans rien d'autre.
* C'est cette version qui accueille les corrections de bugs et les mini-évolutions, y compris ceux issus de nos partenaires techniques sur ce qu'ils détectent et auront déclarés dans la forge.
* Tout bug corrigé ici est également déployé dans 'master' et, éventuellement si toujours pertinent, dans 'develop'.
* Cette branche est 'taguée' régulièrement pour indiquer à tous qu'il y a eu des corrections de bugs.
* C'est sur cette branche que sont passés l'ensemble des tests unitaires (créés dans 'develop'), d'intégration (créés dans 'master') et fonctionnels.
* Les montées de versions sont :
** Scriptés par Maarch pour passer d'une v18.x à une v19.x
** (Semi-) Automatisés pour passer d'une version 19.x à une version 19.y

## Comment je contribue ?
### Signaler des bugs
Cette section vous guide lors de la soumission d'un bogue pour Maarch. 
En suivant ces directives, les responsables de la maintenance et la communauté comprennent votre rapport, reproduisent le comportement et trouvent des rapports connexes.

#### Avant de soumettre un bug
* Consultez la [FAQ](https://community.maarch.org/c/maarch-courrier/f-a-q) sur notre site communautaire. Vous pourriez peut-être trouver la cause du problème et réparer vous-même les choses. Plus important encore, vérifiez si vous pouvez reproduire le problème dans la dernière version de Maarch.
* Déterminez dans quelle fonctionnalité posant problème doit être signalée.
* Effectuez une recherche rapide pour voir si le problème a déjà été signalé. Si c'est le cas, ajoutez un commentaire au problème existant au lieu d'ouvrir un nouveau.


Les bugs peuvent être soumis sur notre [Site communautaire](https://community.maarch.org/c/maarch-courrier).

### Proposer des améliorations
Cette section vous guide lors de la soumission d'une suggestion d'amélioration pour les produits Maarch, incluant des fonctionnalités complètement nouvelles et des améliorations mineures aux fonctionnalités existantes. 

En suivant ces directives, les responsables de la maintenance et la communauté comprennent votre suggestion et trouvent des suggestions connexes.

#### Avant de soumettre une amélioration
* Consultez [Community.maarch.org](http://community.maarch.org) ou [notre documentation](https://docs.maarch.org/) pour obtenir des conseils - vous pourriez découvrir que l'amélioration est déjà disponible. Plus important encore, vérifiez si vous utilisez la dernière version de Maarch Courrier.
* Effectuez une recherche rapide pour voir si l'amélioration a déjà été suggérée. Si c'est le cas, ajoutez un commentaire au ticket existant au lieu d'ouvrir un nouveau.


Les améliorations peuvent être soumises sur notre [Site communautaire](https://community.maarch.org/c/maarch-courrier).

### Et comment je code dans Maarch ?
Il faut suivre le [guide du développeur](https://labs.maarch.org/maarch/MaarchCourrier/wikis/Maarch-Developer-Handbook).

