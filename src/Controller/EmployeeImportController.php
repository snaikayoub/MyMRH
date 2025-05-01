<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\EmployeeSituation;
use App\Form\EmployeeImportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class EmployeeImportController extends AbstractController
{
    #[Route('/admin/import/employees', name: 'import_employees')]
    public function import(Request $request, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        // Augmenter la limite de mémoire et le temps d'exécution
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes
        
        $form = $this->createForm(EmployeeImportType::class);
        $form->handleRequest($request);

        $message = null;
        $errors = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();

            if (($handle = fopen($csvFile->getPathname(), 'r')) !== false) {
                $headers = [];
                $row = 0;
                $count = 0;
                $batchSize = 20; // Traitement par lots

                while (($data = fgetcsv($handle, 0, ";")) !== false) {
                    if ($row === 0) {
                        $headers = array_map('trim', $data);
                        $row++;
                        continue;
                    }

                    // Vérifier si tous les champs attendus sont présents
                    if (count($data) !== count($headers)) {
                        $errors[] = "Ligne $row: Nombre de colonnes incorrect (" . count($data) . " au lieu de " . count($headers) . ")";
                        $row++;
                        continue;
                    }

                    $record = array_combine($headers, $data);

                    if (!$record || !isset($record['matricule']) || empty($record['matricule'])) {
                        $errors[] = "Ligne $row: Format invalide ou matricule manquant";
                        $row++;
                        continue;
                    }

                    try {
                        $employee = new Employee();
                        $employee->setMatricule($record['matricule']);
                        $employee->setNom($record['nom'] ?? '');
                        $employee->setPrenom($record['prenom'] ?? '');
                        
                        // Validation et conversion de la date de naissance avec gestion de multiples formats
                        try {
                            $dateNaissance = null;
                            if (!empty($record['date_naissance'])) {
                                // Nettoyer la date d'éventuels caractères problématiques
                                $dateStr = trim($record['date_naissance']);
                                
                                // Essayer différents formats de date courants
                                $formats = [
                                    'd/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'Y.m.d', 
                                    'd/m/y', 'd-m-y', 'y-m-d', 'd.m.y', 'y.m.d',
                                    'j/n/Y', 'j-n-Y', 'Y-n-j', 'j.n.Y', 'Y.n.j',
                                    'j/n/y', 'j-n-y', 'y-n-j', 'j.n.y', 'y.n.j'
                                ];
                                
                                // Si le format est numérique (excel traite parfois les dates comme des nombres)
                                if (is_numeric($dateStr)) {
                                    // Conversion depuis le format Excel (nombre de jours depuis 1900-01-01)
                                    $dateNaissance = \DateTime::createFromFormat('U', (intval($dateStr) - 25569) * 86400);
                                } else {
                                    foreach ($formats as $format) {
                                        $date = \DateTime::createFromFormat($format, $dateStr);
                                        if ($date && $date->format($format) == $dateStr) {
                                            $dateNaissance = $date;
                                            break;
                                        }
                                    }
                                    
                                    // Essayer avec strtotime en dernier recours
                                    if (!$dateNaissance) {
                                        $timestamp = strtotime($dateStr);
                                        if ($timestamp !== false) {
                                            $dateNaissance = new \DateTime();
                                            $dateNaissance->setTimestamp($timestamp);
                                        }
                                    }
                                }
                                
                                // Vérifier si la date est valide (entre 1900 et aujourd'hui)
                                if ($dateNaissance) {
                                    $minDate = new \DateTime('1900-01-01');
                                    $maxDate = new \DateTime();
                                    
                                    if ($dateNaissance < $minDate || $dateNaissance > $maxDate) {
                                        throw new \Exception("Date en dehors des limites raisonnables");
                                    }
                                } else {
                                    throw new \Exception("Format non reconnu");
                                }
                            }
                            
                            $employee->setDateNaissance($dateNaissance);
                        } catch (\Exception $e) {
                            $errors[] = "Ligne $row: Format de date de naissance invalide (" . $record['date_naissance'] . ") - " . $e->getMessage();
                            $row++;
                            continue;
                        }
                        
                        $employee->setLieuNaissance($record['lieu_naissance'] ?? null);
                        $employee->setCodeSexe($record['code_sexe'] ?? '');
                        $employee->setCin($record['cin'] ?? '');
                        
                        // Validation et conversion de la date d'embauche avec gestion de multiples formats
                        try {
                            $dateEmbauche = null;
                            if (!empty($record['date_embauche'])) {
                                // Nettoyer la date d'éventuels caractères problématiques
                                $dateStr = trim($record['date_embauche']);
                                
                                // Essayer différents formats de date courants
                                $formats = [
                                    'd/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'Y.m.d', 
                                    'd/m/y', 'd-m-y', 'y-m-d', 'd.m.y', 'y.m.d',
                                    'j/n/Y', 'j-n-Y', 'Y-n-j', 'j.n.Y', 'Y.n.j',
                                    'j/n/y', 'j-n-y', 'y-n-j', 'j.n.y', 'y.n.j'
                                ];
                                
                                // Si le format est numérique (excel traite parfois les dates comme des nombres)
                                if (is_numeric($dateStr)) {
                                    // Conversion depuis le format Excel (nombre de jours depuis 1900-01-01)
                                    $dateEmbauche = \DateTime::createFromFormat('U', (intval($dateStr) - 25569) * 86400);
                                } else {
                                    foreach ($formats as $format) {
                                        $date = \DateTime::createFromFormat($format, $dateStr);
                                        if ($date && $date->format($format) == $dateStr) {
                                            $dateEmbauche = $date;
                                            break;
                                        }
                                    }
                                    
                                    // Essayer avec strtotime en dernier recours
                                    if (!$dateEmbauche) {
                                        $timestamp = strtotime($dateStr);
                                        if ($timestamp !== false) {
                                            $dateEmbauche = new \DateTime();
                                            $dateEmbauche->setTimestamp($timestamp);
                                        }
                                    }
                                }
                                
                                // Vérifier si la date est valide (entre 1950 et aujourd'hui)
                                if ($dateEmbauche) {
                                    $minDate = new \DateTime('1950-01-01');
                                    $maxDate = new \DateTime();
                                    
                                    if ($dateEmbauche < $minDate || $dateEmbauche > $maxDate) {
                                        throw new \Exception("Date en dehors des limites raisonnables");
                                    }
                                } else {
                                    throw new \Exception("Format non reconnu");
                                }
                            }
                            
                            $employee->setDateEmbauche($dateEmbauche);
                        } catch (\Exception $e) {
                            $errors[] = "Ligne $row: Format de date d'embauche invalide (" . $record['date_embauche'] . ") - " . $e->getMessage();
                            $row++;
                            continue;
                        }
                        
                        $employee->setAdresse($record['adresse'] ?? null);

                        $situation = new EmployeeSituation();
                        $situation->setEmployee($employee);
                        // Pour la situation, utilisez la date actuelle si la date d'embauche est null
                        $situation->setStartDate($dateEmbauche ?? new \DateTime());
                        $situation->setNatureChangement($record['natureChangement'] ?? '');
                        $situation->setGrade($record['grade'] ?? '');
                        $situation->setAffectation($record['affectation'] ?? '');
                        $situation->setCategorie($record['categorie'] ?? '');
                        $situation->setSitFamiliale($record['sitFamiliale'] ?? '');
                        
                        // Conversion sécurisée des valeurs numériques
                        $situation->setEnf(!empty($record['enf']) && is_numeric($record['enf']) ? (int) $record['enf'] : 0);
                        $situation->setEnfCharge(!empty($record['enf_charge']) && is_numeric($record['enf_charge']) ? (int) $record['enf_charge'] : 0);
                        $situation->setTauxHoraire(!empty($record['tauxHoraire']) && is_numeric($record['tauxHoraire']) ? (float) $record['tauxHoraire'] : 0.0);
                        $situation->setTypePaie($record['type_paie'] ?? '');

                        $em->persist($employee);
                        $em->persist($situation);

                        $count++;
                        
                        // Libérer la mémoire tous les X enregistrements
                        if ($count % $batchSize === 0) {
                            $em->flush();
                            $em->clear(); // Détache toutes les entités pour libérer la mémoire
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Ligne $row: " . $e->getMessage();
                        $logger->error("Erreur d'importation à la ligne $row: " . $e->getMessage());
                    }

                    $row++;
                }

                fclose($handle);
                
                // Persistance finale des entités restantes
                $em->flush();

                $message = "$count employés importés avec succès sur " . ($row - 1) . " lignes traitées.";
                
                if (!empty($errors)) {
                    $this->addFlash('warning', count($errors) . " erreurs rencontrées lors de l'importation.");
                }
            }
        }

        return $this->render('employee/import.html.twig', [
            'form' => $form->createView(),
            'message' => $message,
            'errors' => $errors,
        ]);
    }
}