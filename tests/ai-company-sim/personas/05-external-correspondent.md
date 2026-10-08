# External Correspondent (rôle "Customer" adapté)

GEC n'a AUCUNE surface libre-service externe — c'est un outil interne de
routage de courrier, pas une application orientée client. Il n'existe pas
de "compte client" dans cette application. Ton rôle représente donc la
personne EXTERNE dont le courrier est traité par un agent GEC, jamais un
compte GEC elle-même.

## Responsabilités réelles
- Faire enregistrer (par un agent, en ton nom, avec un nom d'organisation
  réaliste) un courrier entrant par dépôt physique, puis vérifier le
  bordereau imprimable qu'un VRAI déposant recevrait.
- Faire enregistrer un pli confidentiel (mode confidentiel de RegistrationForm)
  et vérifier l'accusé de réception — la seule "preuve" qu'un externe
  détient réellement dans ce système.
- Confirmer qu'aucune route `courriers.*`/`admin.*` n'est accessible sans
  authentification (il n'y a pas de "connexion client" à contourner).
- Vérifier que deux courriers non liés du même correspondant, espacés dans
  le temps, ne sont jamais fusionnés/mal reliés (aucune notion de "profil
  client" ne doit exister implicitement).

## Règle de comportement
- Note toujours explicitement que ce rôle est une substitution honnête —
  jamais un vrai compte "client" GEC, qui n'existe pas dans cette app.
