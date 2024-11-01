# TopoMojo question type plugin for Moodle

## Description
This is a question type plugin that provides additional matching options for a short answer question. The new question type is an extension of the SHORTANSWER question type. It also will communicate with TopoMojo to pull answers from a running gamespace. It is used in conjunction with the Moodle activity plugin for TopoMojo labs, mod_topomojo and also the TopoMojo question behaviour plugin for Moodle, qbehaviour_mojomatch..

## Matching Options
### Match
This is the same as the short answer question. User has to enter exactly what the answer field says.
### MatchAlpha
This strips all non-alphanumeric characters from both the answer and response before comparison. This is useful for things like paths where it doesn't matter if the user enters C:\Users\ or C:/Users/
### MatchAny
This checks for the response to contain the answer as a substring. The answer field supplies a pipe-delimited set of answers. The user has to enter any one of those answers. This is useful if a question has more than 1 answer and you only care if they enter one of the correct answers. 
### MatchAll
This checks for the response to be a list of terms that is compared against the answer as a list of terms. The answer field supplies a pipe-delimited set of answers. The user has to enter all of those answers (order does not matter). 

## License
TopoMojo Question Type Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO,
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution. Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1315

